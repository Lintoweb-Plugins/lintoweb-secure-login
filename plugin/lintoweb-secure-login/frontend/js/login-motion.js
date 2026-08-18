(function () {
	'use strict';

	if (!document.body || !document.body.classList.contains('lsl-login')) {
		return;
	}

	var network = document.querySelector('.lsl-login-network');
	var canvas = network ? network.querySelector('.lsl-network-canvas') : null;
	var login = document.getElementById('login');

	if (!network || !canvas || !login) {
		return;
	}

	var ctx = canvas.getContext('2d');
	if (!ctx) {
		return;
	}

	var reducedMotionQuery = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
	var prefersReducedMotion = reducedMotionQuery ? reducedMotionQuery.matches : false;
	var coarsePointerQuery = window.matchMedia ? window.matchMedia('(pointer: coarse)') : null;

	var viewport = {
		width: Math.max(1, window.innerWidth),
		height: Math.max(1, window.innerHeight),
		dpr: 1
	};

	var mouse = {
		x: viewport.width * 0.5,
		y: viewport.height * 0.5,
		active: false,
		insideCard: false
	};

	var targetParallax = { x: 0, y: 0 };
	var parallax = { x: 0, y: 0 };
	var particles = [];
	var frameId = 0;
	var lastTime = performance.now();
	var resizeTimer = 0;
	var loginRect = null;
	var pointerCoarse = coarsePointerQuery ? coarsePointerQuery.matches : false;

	var COLORS = {
		cyan: { r: 72, g: 208, b: 236 },
		blue: { r: 23, g: 95, b: 214 },
		pink: { r: 253, g: 124, b: 131 },
		gold: { r: 236, g: 152, b: 40 }
	};

	function clamp(value, min, max) {
		return Math.max(min, Math.min(max, value));
	}

	function lerp(current, target, amount) {
		return current + ((target - current) * amount);
	}

	function distance(x1, y1, x2, y2) {
		var dx = x2 - x1;
		var dy = y2 - y1;
		return Math.sqrt((dx * dx) + (dy * dy));
	}

	function colorForIndex(index, count) {
		var ratio = index / Math.max(count - 1, 1);

		/* Approx. 82% cyan, 13% blue, <=5% accent colors. */
		if (ratio < 0.82) {
			return COLORS.cyan;
		}
		if (ratio < 0.95) {
			return COLORS.blue;
		}
		return (index % 2 === 0) ? COLORS.pink : COLORS.gold;
	}

	function particleCount() {
		if (viewport.width <= 560) {
			return 72;
		}
		if (viewport.width <= 900) {
			return 102;
		}

		return clamp(
			Math.round((viewport.width * viewport.height) / 5000),
			105,
			180
		);
	}

	function connectionDistance() {
		var shortestSide = Math.min(viewport.width, viewport.height);

		if (shortestSide <= 560) {
			return 112;
		}
		if (shortestSide <= 900) {
			return 128;
		}

		return clamp(shortestSide * 0.17, 145, 175);
	}

	function connectionOpacity(distanceValue, threshold) {
		var fade = 1 - (distanceValue / threshold);
		return clamp(fade, 0, 1);
	}

	function updateLoginRect() {
		var rect = login.getBoundingClientRect();
		loginRect = {
			left: rect.left,
			top: rect.top,
			right: rect.right,
			bottom: rect.bottom
		};
	}

	function circleAvoidance(particle, dt, strength) {
		if (!loginRect) {
			return;
		}

		var padding = 38;
		var left = loginRect.left - padding;
		var right = loginRect.right + padding;
		var top = loginRect.top - padding;
		var bottom = loginRect.bottom + padding;

		var nearestX = clamp(particle.x, left, right);
		var nearestY = clamp(particle.y, top, bottom);
		var dx = particle.x - nearestX;
		var dy = particle.y - nearestY;
		var distanceToCard = Math.sqrt((dx * dx) + (dy * dy));

		var radius = 115;
		if (distanceToCard <= 0 || distanceToCard >= radius) {
			return;
		}

		var falloff = 1 - (distanceToCard / radius);
		var normalizedX = dx / Math.max(distanceToCard, 1);
		var normalizedY = dy / Math.max(distanceToCard, 1);
		var push = falloff * falloff * 0.024 * dt * strength;

		particle.vx += normalizedX * push;
		particle.vy += normalizedY * push;
	}

	function createParticle(index, count) {
		var angle = Math.random() * Math.PI * 2;
		var speed = 0.010 + (Math.random() * 0.016);
		var color = colorForIndex(index, count);

		return {
			x: Math.random() * viewport.width,
			y: Math.random() * viewport.height,
			vx: Math.cos(angle) * speed,
			vy: Math.sin(angle) * speed,
			seed: Math.random() * 1000,
			phase: Math.random() * Math.PI * 2,
			radius: 1.3 + (Math.random() * 1.5),
			opacity: 0.42 + (Math.random() * 0.22),
			color: color
		};
	}

	function seedParticles() {
		var count = particleCount();
		particles.length = 0;

		for (var i = 0; i < count; i++) {
			particles.push(createParticle(i, count));
		}
	}

	function resizeCanvas() {
		viewport.width = Math.max(1, window.innerWidth);
		viewport.height = Math.max(1, window.innerHeight);
		viewport.dpr = Math.min(window.devicePixelRatio || 1, 1.75);

		canvas.width = Math.round(viewport.width * viewport.dpr);
		canvas.height = Math.round(viewport.height * viewport.dpr);
		canvas.style.width = viewport.width + 'px';
		canvas.style.height = viewport.height + 'px';

		ctx.setTransform(viewport.dpr, 0, 0, viewport.dpr, 0, 0);

		updateLoginRect();
		seedParticles();

		if (prefersReducedMotion) {
			render(performance.now());
		}
	}

	function triggerRipple(x, y) {
		if (pointerCoarse || prefersReducedMotion) {
			return;
		}

		var ripple = network.querySelector('.lsl-network-ripple');
		if (!ripple) {
			return;
		}

		ripple.style.left = x + 'px';
		ripple.style.top = y + 'px';
		ripple.classList.remove('is-active');

		/* Force a reflow so consecutive ripples can restart cleanly. */
		void ripple.offsetWidth;
		ripple.classList.add('is-active');
	}

	function onPointerMove(event) {
		setMousePosition(event.clientX, event.clientY, true);
	}

	function onPointerLeave() {
		mouse.active = false;
		mouse.insideCard = false;
		targetParallax.x = 0;
		targetParallax.y = 0;
	}

	function onPointerDown(event) {
		triggerRipple(event.clientX, event.clientY);
	}

	function onTouchStart(event) {
		if (!event.touches || !event.touches.length) {
			return;
		}

		var touch = event.touches[0];
		setMousePosition(touch.clientX, touch.clientY, true);
		triggerRipple(touch.clientX, touch.clientY);
	}

	function onTouchMove(event) {
		if (!event.touches || !event.touches.length) {
			return;
		}

		var touch = event.touches[0];
		setMousePosition(touch.clientX, touch.clientY, true);
	}

	function onTouchEnd() {
		mouse.active = false;
		targetParallax.x = 0;
		targetParallax.y = 0;
	}

	function updateParticle(particle, delta, now) {
		var timeScale = delta / 16.667;
		var breathing = Math.sin((now * 0.00018) + particle.seed);
		var steeringX = Math.sin((now * 0.00011) + particle.seed) * 0.00085;
		var steeringY = Math.cos((now * 0.00013) + particle.phase) * 0.00085;

		particle.vx += steeringX * timeScale;
		particle.vy += steeringY * timeScale;

		/* Very gentle speed normalization keeps the network organic instead of frantic. */
		var speed = Math.sqrt((particle.vx * particle.vx) + (particle.vy * particle.vy));
		var minSpeed = 0.006;
		var maxSpeed = 0.026;
		if (speed > maxSpeed) {
			var maxScale = maxSpeed / speed;
			particle.vx *= maxScale;
			particle.vy *= maxScale;
		} else if (speed < minSpeed) {
			var minScale = minSpeed / Math.max(speed, 0.00001);
			particle.vx *= minScale;
			particle.vy *= minScale;
		}

		var magneticRadius = pointerCoarse ? 0 : 170;
		if (mouse.active && magneticRadius > 0) {
			var dx = mouse.x - particle.x;
			var dy = mouse.y - particle.y;
			var mouseDistance = Math.sqrt((dx * dx) + (dy * dy));

			if (mouseDistance > 0 && mouseDistance < magneticRadius) {
				var falloff = 1 - (mouseDistance / magneticRadius);
				var pull = falloff * falloff * 0.0125 * timeScale;
				particle.vx += (dx / mouseDistance) * pull;
				particle.vy += (dy / mouseDistance) * pull;
			}
		}

		/* Make the login card a quiet center: particles subtly ease away nearby. */
		circleAvoidance(particle, delta, mouse.insideCard ? 0.42 : 1);

		particle.x += particle.vx * delta;
		particle.y += particle.vy * delta;

		/* Soft bounce with a hard safety clamp; nodes can never leave the viewport. */
		if (particle.x <= 0) {
			particle.x = 0;
			particle.vx = Math.abs(particle.vx) * 0.92;
		} else if (particle.x >= viewport.width) {
			particle.x = viewport.width;
			particle.vx = -Math.abs(particle.vx) * 0.92;
		}

		if (particle.y <= 0) {
			particle.y = 0;
			particle.vy = Math.abs(particle.vy) * 0.92;
		} else if (particle.y >= viewport.height) {
			particle.y = viewport.height;
			particle.vy = -Math.abs(particle.vy) * 0.92;
		}

		particle.currentOpacity = clamp(
			particle.opacity + (breathing * 0.025),
			0.18,
			0.62
		);
	}

	function effectiveParticlePosition(particle) {
		var depth = 0.35 + ((particle.radius - 0.65) / 0.75) * 0.65;
		return {
			x: particle.x + (parallax.x * depth),
			y: particle.y + (parallax.y * depth)
		};
	}

	function drawNode(particle, position) {
		var highlightDistance = mouse.active
			? distance(position.x, position.y, mouse.x, mouse.y)
			: Infinity;
		var highlight = highlightDistance < 150
			? 1 - (highlightDistance / 150)
			: 0;

		var radius = particle.radius + (highlight * 0.95);
		var alpha = particle.currentOpacity + (highlight * 0.22);
		var rgb = particle.color;

		if (highlight > 0.02) {
			ctx.beginPath();
			ctx.fillStyle = 'rgba(' + rgb.r + ',' + rgb.g + ',' + rgb.b + ',' + (alpha * 0.14).toFixed(3) + ')';
			ctx.arc(position.x, position.y, radius * 5.8, 0, Math.PI * 2);
			ctx.fill();
		}

		ctx.beginPath();
		ctx.fillStyle = 'rgba(' + rgb.r + ',' + rgb.g + ',' + rgb.b + ',' + clamp(alpha, 0, 0.88).toFixed(3) + ')';
		ctx.arc(position.x, position.y, radius, 0, Math.PI * 2);
		ctx.fill();

		ctx.beginPath();
		ctx.fillStyle = 'rgba(250,251,254,' + (0.64 + (highlight * 0.20)).toFixed(3) + ')';
		ctx.arc(position.x - (radius * 0.2), position.y - (radius * 0.2), Math.max(0.42, radius * 0.27), 0, Math.PI * 2);
		ctx.fill();
	}

	function drawConnection(a, b, threshold) {
		var dx = b.position.x - a.position.x;
		var dy = b.position.y - a.position.y;
		var dist = Math.sqrt((dx * dx) + (dy * dy));

		if (dist > threshold) {
			return;
		}

		var alpha = connectionOpacity(dist, threshold) * 0.28;
		var midpointX = (a.position.x + b.position.x) * 0.5;
		var midpointY = (a.position.y + b.position.y) * 0.5;
		var mouseDist = mouse.active
			? distance(midpointX, midpointY, mouse.x, mouse.y)
			: Infinity;

		if (mouseDist < 180) {
			alpha += (1 - (mouseDist / 180)) * 0.20;
		}

		if (mouse.insideCard) {
			alpha *= 0.78;
		}

		ctx.beginPath();
		ctx.moveTo(a.position.x, a.position.y);
		ctx.lineTo(b.position.x, b.position.y);

		var highlight = mouseDist < 165 ? 1 - (mouseDist / 165) : 0;
		var rgb = highlight > 0.03 ? COLORS.cyan : a.particle.color;

		ctx.strokeStyle = 'rgba(' + rgb.r + ',' + rgb.g + ',' + rgb.b + ',' + clamp(alpha, 0.028, 0.46).toFixed(3) + ')';
		ctx.lineWidth = highlight > 0.03 ? 1.7 : 1.3;
		ctx.stroke();
	}

	function render(now) {
		ctx.clearRect(0, 0, viewport.width, viewport.height);

		parallax.x = lerp(parallax.x, prefersReducedMotion ? 0 : targetParallax.x, 0.055);
		parallax.y = lerp(parallax.y, prefersReducedMotion ? 0 : targetParallax.y, 0.055);

		network.style.setProperty('--lsl-parallax-x', parallax.x.toFixed(2) + 'px');
		network.style.setProperty('--lsl-parallax-y', parallax.y.toFixed(2) + 'px');

		var threshold = connectionDistance();
		var drawParticles = [];

		for (var i = 0; i < particles.length; i++) {
			var particle = particles[i];

			drawParticles.push({
				particle: particle,
				position: effectiveParticlePosition(particle)
			});
		}

		/* Connection pass; 60 nodes means only ~1770 pairs in the worst case. */
		for (var a = 0; a < drawParticles.length; a++) {
			for (var b = a + 1; b < drawParticles.length; b++) {
				drawConnection(drawParticles[a], drawParticles[b], threshold);
			}
		}

		for (var p = 0; p < drawParticles.length; p++) {
			drawNode(drawParticles[p].particle, drawParticles[p].position);
		}
	}

	function frame(now) {
		var delta = Math.min(now - lastTime, 34);
		lastTime = now;

		if (!prefersReducedMotion) {
			for (var i = 0; i < particles.length; i++) {
				updateParticle(particles[i], delta, now);
			}
		}

		render(now);
		frameId = window.requestAnimationFrame(frame);
	}

	function rebuild() {
		viewport.width = Math.max(1, window.innerWidth);
		viewport.height = Math.max(1, window.innerHeight);
		viewport.dpr = Math.min(window.devicePixelRatio || 1, 1.75);

		canvas.width = Math.round(viewport.width * viewport.dpr);
		canvas.height = Math.round(viewport.height * viewport.dpr);
		canvas.style.width = viewport.width + 'px';
		canvas.style.height = viewport.height + 'px';
		ctx.setTransform(viewport.dpr, 0, 0, viewport.dpr, 0, 0);

		updateLoginRect();

		var count = particleCount();
		particles.length = 0;

		for (var i = 0; i < count; i++) {
			particles.push(createParticle(i, count));
		}

		if (prefersReducedMotion) {
			render(performance.now());
		}
	}

	function onResize() {
		window.clearTimeout(resizeTimer);
		resizeTimer = window.setTimeout(rebuild, 90);
	}

	function onPointerMove(event) {
		setMousePosition(event.clientX, event.clientY, true);
	}

	function setMousePosition(x, y, active) {
		mouse.x = clamp(x, 0, viewport.width);
		mouse.y = clamp(y, 0, viewport.height);
		mouse.active = active;
		mouse.insideCard =
			mouse.x >= loginRect.left &&
			mouse.x <= loginRect.right &&
			mouse.y >= loginRect.top &&
			mouse.y <= loginRect.bottom;

		var nx = ((mouse.x / viewport.width) - 0.5) * 2;
		var ny = ((mouse.y / viewport.height) - 0.5) * 2;

		targetParallax.x = clamp(nx * 7, -7, 7);
		targetParallax.y = clamp(ny * 5, -5, 5);
	}

	function onPointerLeave() {
		mouse.active = false;
		mouse.insideCard = false;
		targetParallax.x = 0;
		targetParallax.y = 0;
	}

	function onTouchStart(event) {
		if (!event.touches || !event.touches.length) {
			return;
		}

		var touch = event.touches[0];
		setMousePosition(touch.clientX, touch.clientY, true);
		triggerRipple(touch.clientX, touch.clientY);
	}

	function onTouchMove(event) {
		if (!event.touches || !event.touches.length) {
			return;
		}

		var touch = event.touches[0];
		setMousePosition(touch.clientX, touch.clientY, true);
	}

	function onTouchEnd() {
		onPointerLeave();
	}

	function updateReducedMotion() {
		prefersReducedMotion = reducedMotionQuery ? reducedMotionQuery.matches : false;

		if (prefersReducedMotion) {
			targetParallax.x = 0;
			targetParallax.y = 0;
			render(performance.now());
		}
	}

	window.addEventListener('resize', onResize, { passive: true });
	window.addEventListener('pointermove', onPointerMove, { passive: true });
	window.addEventListener('pointerdown', function (event) {
		triggerRipple(event.clientX, event.clientY);
	}, { passive: true });
	window.addEventListener('pointerleave', onPointerLeave, { passive: true });
	window.addEventListener('touchstart', onTouchStart, { passive: true });
	window.addEventListener('touchmove', onTouchMove, { passive: true });
	window.addEventListener('touchend', onTouchEnd, { passive: true });
	window.addEventListener('touchcancel', onTouchEnd, { passive: true });

	if (reducedMotionQuery) {
		if (typeof reducedMotionQuery.addEventListener === 'function') {
			reducedMotionQuery.addEventListener('change', updateReducedMotion);
		} else if (typeof reducedMotionQuery.addListener === 'function') {
			reducedMotionQuery.addListener(updateReducedMotion);
		}
	}

	document.addEventListener('visibilitychange', function () {
		if (document.hidden) {
			if (frameId) {
				window.cancelAnimationFrame(frameId);
				frameId = 0;
			}
		} else if (!prefersReducedMotion && !frameId) {
			lastTime = performance.now();
			frameId = window.requestAnimationFrame(frame);
		}
	});

	/* Initialize after the login layout has been measured. */
	window.requestAnimationFrame(function () {
		rebuild();

		if (prefersReducedMotion) {
			return;
		}

		lastTime = performance.now();
		frameId = window.requestAnimationFrame(frame);
	});

	window.addEventListener('pagehide', function () {
		if (frameId) {
			window.cancelAnimationFrame(frameId);
			frameId = 0;
		}
	}, { passive: true });
})();

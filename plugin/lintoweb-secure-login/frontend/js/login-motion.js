(function () {
	'use strict';

	if (!document.body || !document.body.classList.contains('lsl-login')) {
		return;
	}

	var layer = document.querySelector('.lsl-login-orbs');
	if (!layer) {
		return;
	}

	var orbs = Array.prototype.slice.call(layer.querySelectorAll('.lsl-orb'));
	if (!orbs.length) {
		return;
	}

	var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var dpr = Math.min(window.devicePixelRatio || 1, 2);
	var viewport = { width: window.innerWidth, height: window.innerHeight };

	var profiles = [
		{ speed: 0.020, minSpeed: 0.006, maxSpeed: 0.043, steer: 0.00135, edge: 0.16, scaleSpeed: 0.00019, phase: 0.20 },
		{ speed: 0.016, minSpeed: 0.005, maxSpeed: 0.037, steer: 0.00118, edge: 0.18, scaleSpeed: 0.00016, phase: 1.70 },
		{ speed: 0.013, minSpeed: 0.004, maxSpeed: 0.032, steer: 0.00104, edge: 0.20, scaleSpeed: 0.00014, phase: 3.10 },
		{ speed: 0.019, minSpeed: 0.006, maxSpeed: 0.041, steer: 0.00127, edge: 0.15, scaleSpeed: 0.00018, phase: 4.80 },
		{ speed: 0.011, minSpeed: 0.003, maxSpeed: 0.028, steer: 0.00098, edge: 0.22, scaleSpeed: 0.00012, phase: 5.90 }
	];

	var states = [];
	var lastTime = performance.now();
	var frameId = 0;
	var resizeTimer = 0;

	function clamp(value, min, max) {
		return Math.max(min, Math.min(max, value));
	}

	function length(x, y) {
		return Math.sqrt((x * x) + (y * y));
	}

	function normalize(x, y) {
		var len = length(x, y);
		if (!len) {
			return { x: 1, y: 0 };
		}
		return { x: x / len, y: y / len };
	}

	function getMetrics(el) {
		var rect = el.getBoundingClientRect();
		var width = rect.width || 320;
		var height = rect.height || 320;
		return {
			width: width,
			height: height,
			radiusX: width / 2,
			radiusY: height / 2,
			marginX: (width / 2) * 1.18,
			marginY: (height / 2) * 1.18
		};
	}

	function safeBounds(state) {
		var xMin = state.metrics.marginX;
		var xMax = viewport.width - state.metrics.marginX;
		var yMin = state.metrics.marginY;
		var yMax = viewport.height - state.metrics.marginY;

		if (xMax < xMin) {
			xMin = viewport.width / 2;
			xMax = xMin;
		}
		if (yMax < yMin) {
			yMin = viewport.height / 2;
			yMax = yMin;
		}

		return { xMin: xMin, xMax: xMax, yMin: yMin, yMax: yMax };
	}

	function applyTransform(state, now) {
		var breathe = 1 + Math.sin((now * state.profile.scaleSpeed) + state.profile.phase) * 0.055;
		var drift = Math.sin((now * 0.00018) + state.profile.phase) * 0.85;
		var opacity = state.baseOpacity + (Math.sin((now * 0.00016) + state.profile.phase * 1.7) * 0.045);

		state.el.style.transform = 'translate3d(' + state.x.toFixed(2) + 'px,' + state.y.toFixed(2) + 'px,0) translate3d(-50%,-50%,0) scale(' + (breathe + drift * 0.002).toFixed(4) + ') rotate(' + state.rotation.toFixed(2) + 'deg)';
		state.el.style.opacity = clamp(opacity, 0.20, 0.80).toFixed(3);
	}

	function createState(el, index) {
		var profile = profiles[index % profiles.length];
		var metrics = getMetrics(el);
		var bounds = safeBounds({ metrics: metrics });
		var startX = bounds.xMin + ((bounds.xMax - bounds.xMin) * [0.14, 0.78, 0.56, 0.84, 0.34][index]);
		var startY = bounds.yMin + ((bounds.yMax - bounds.yMin) * [0.24, 0.22, 0.72, 0.62, 0.48][index]);
		var seedAngle = [0.58, 2.20, 3.34, 4.32, 5.46][index];
		var speed = profile.speed;

		return {
			el: el,
			profile: profile,
			metrics: metrics,
			x: startX,
			y: startY,
			vx: Math.cos(seedAngle) * speed,
			vy: Math.sin(seedAngle) * speed,
			noiseX: (index + 1) * 0.72,
			noiseY: (index + 1) * 1.13,
			phase: profile.phase,
			baseOpacity: [0.56, 0.47, 0.43, 0.38, 0.30][index],
			rotation: [-8, 7, -5, 10, -4][index],
			initialSpeed: speed
		};
	}

	states = orbs.map(createState);

	function refreshBounds() {
		viewport.width = window.innerWidth;
		viewport.height = window.innerHeight;
		states.forEach(function (state) {
			state.metrics = getMetrics(state.el);
			var bounds = safeBounds(state);
			state.x = clamp(state.x, bounds.xMin, bounds.xMax);
			state.y = clamp(state.y, bounds.yMin, bounds.yMax);
		});
	}

	function updateState(state, delta, now) {
		var bounds = safeBounds(state);
		var width = Math.max(bounds.xMax - bounds.xMin, 1);
		var height = Math.max(bounds.yMax - bounds.yMin, 1);
		var nx = state.x / width;
		var ny = state.y / height;
		var edgeX = Math.min((state.x - bounds.xMin) / width, (bounds.xMax - state.x) / width);
		var edgeY = Math.min((state.y - bounds.yMin) / height, (bounds.yMax - state.y) / height);
		var edgeFactorX = clamp((state.profile.edge - edgeX) / state.profile.edge, 0, 1);
		var edgeFactorY = clamp((state.profile.edge - edgeY) / state.profile.edge, 0, 1);
		var wave = now * 0.000085;

		// Gentle ambient steering: curved, continuous movement instead of point-to-point keyframes.
		var targetX = Math.sin((wave * 1.17) + state.noiseX) * 0.65 + Math.cos((wave * 0.53) + state.phase) * 0.35;
		var targetY = Math.cos((wave * 0.93) + state.noiseY) * 0.62 + Math.sin((wave * 0.47) + state.phase * 1.4) * 0.38;
		var desired = normalize(targetX, targetY);

		// Soft boundary steering. The closer the orb gets to a wall, the more it curves inward.
		var steerX = 0;
		var steerY = 0;
		if (edgeFactorX > 0) {
			steerX += (state.x < (viewport.width / 2) ? 1 : -1) * edgeFactorX;
		}
		if (edgeFactorY > 0) {
			steerY += (state.y < (viewport.height / 2) ? 1 : -1) * edgeFactorY;
		}

		var steer = normalize((desired.x * 0.85) + (steerX * 1.55), (desired.y * 0.85) + (steerY * 1.55));
		var currentSpeed = length(state.vx, state.vy);
		var desiredSpeed = clamp(currentSpeed, state.profile.minSpeed, state.profile.maxSpeed);
		var targetVx = steer.x * desiredSpeed;
		var targetVy = steer.y * desiredSpeed;
		var blend = 1 - Math.exp(-state.profile.steer * delta);

		state.vx += (targetVx - state.vx) * blend;
		state.vy += (targetVy - state.vy) * blend;

		// Keep the trajectory gently curved even in the middle of the viewport.
		var tangent = Math.sin((now * 0.00011) + state.phase) * 0.00016 * delta;
		var rotatedX = (state.vx * Math.cos(tangent)) - (state.vy * Math.sin(tangent));
		var rotatedY = (state.vx * Math.sin(tangent)) + (state.vy * Math.cos(tangent));
		state.vx = rotatedX;
		state.vy = rotatedY;

		state.x += state.vx * delta;
		state.y += state.vy * delta;

		// Hard safety clamp: even with a resize or a missed frame, the visible orb cannot leave the viewport.
		if (state.x <= bounds.xMin) {
			state.x = bounds.xMin;
			state.vx = Math.abs(state.vx) * 0.62 + state.profile.minSpeed * 0.38;
		}
		if (state.x >= bounds.xMax) {
			state.x = bounds.xMax;
			state.vx = -Math.abs(state.vx) * 0.62 - state.profile.minSpeed * 0.38;
		}
		if (state.y <= bounds.yMin) {
			state.y = bounds.yMin;
			state.vy = Math.abs(state.vy) * 0.62 + state.profile.minSpeed * 0.38;
		}
		if (state.y >= bounds.yMax) {
			state.y = bounds.yMax;
			state.vy = -Math.abs(state.vy) * 0.62 - state.profile.minSpeed * 0.38;
		}

		state.rotation += Math.sin((now * 0.00009) + state.phase) * 0.0016 * delta;
		applyTransform(state, now);
	}

	function renderStatic() {
		states.forEach(function (state) {
			applyTransform(state, performance.now());
		});
	}

	function frame(now) {
		var delta = Math.min(now - lastTime, 34);
		lastTime = now;

		states.forEach(function (state) {
			updateState(state, delta, now);
		});

		frameId = window.requestAnimationFrame(frame);
	}

	function onResize() {
		window.clearTimeout(resizeTimer);
		resizeTimer = window.setTimeout(function () {
			refreshBounds();
		}, 80);
	}

	window.addEventListener('resize', onResize, { passive: true });

	refreshBounds();

	if (prefersReducedMotion) {
		renderStatic();
		return;
	}

	frameId = window.requestAnimationFrame(frame);

	window.addEventListener('pagehide', function () {
		if (frameId) {
			window.cancelAnimationFrame(frameId);
		}
	}, { passive: true });
})();

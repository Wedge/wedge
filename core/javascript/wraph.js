/*!
 * Wraph (Wedge Graphs)
 *
 * Modified and released under the Wedge license by Nao
 * http://wedge.org/license/
 *
 * Based on Chart.js
 * http://chartjs.org/
 *
 * Copyright 2013 Nick Downie
 * Released under the MIT license
 * https://github.com/nnnick/Chart.js/blob/master/LICENSE.md
 */

// Define the global Wraph variable as a class.
// It expects a canvas context as its first parameter.
window.Wraph = function (cx, options, zoom_hook)
{
	var chart = this;

	// Easing functions adapted from Robert Penner's easing equations
	// http://www.robertpenner.com/easing/

	var animationOptions = {
		linear: function (t) {
			return t;
		},
		easeInQuad: function (t) {
			return t*t;
		},
		easeOutQuad: function (t) {
			return -1*t*(t-2);
		},
		easeInOutQuad: function (t) {
			if ((t/=1/2) < 1) return 1/2*t*t;
			return -1/2 * ((--t)*(t-2) - 1);
		},
		easeInCubic: function (t) {
			return t*t*t;
		},
		easeOutCubic: function (t) {
			return 1*((t=t/1-1)*t*t + 1);
		},
		easeInOutCubic: function (t) {
			if ((t/=1/2) < 1) return 1/2*t*t*t;
			return 1/2*((t-=2)*t*t + 2);
		},
		easeInQuart: function (t) {
			return t*t*t*t;
		},
		easeOutQuart: function (t) {
			return -1 * ((t=t/1-1)*t*t*t - 1);
		},
		easeInOutQuart: function (t) {
			if ((t/=1/2) < 1) return 1/2*t*t*t*t;
			return -1/2 * ((t-=2)*t*t*t - 2);
		},
		easeInQuint: function (t) {
			return 1*(t/=1)*t*t*t*t;
		},
		easeOutQuint: function (t) {
			return 1*((t=t/1-1)*t*t*t*t + 1);
		},
		easeInOutQuint: function (t) {
			if ((t/=1/2) < 1) return 1/2*t*t*t*t*t;
			return 1/2*((t-=2)*t*t*t*t + 2);
		},
		easeInSine: function (t) {
			return -1 * Math.cos(t/1 * (Math.PI/2)) + 1;
		},
		easeOutSine: function (t) {
			return 1 * Math.sin(t/1 * (Math.PI/2));
		},
		easeInOutSine: function (t) {
			return -1/2 * (Math.cos(Math.PI*t/1) - 1);
		},
		easeInExpo: function (t) {
			return t == 0 ? 1 : 1 * Math.pow(2, 10 * (t/1 - 1));
		},
		easeOutExpo: function (t) {
			return t == 1 ? 1 : 1 * (-Math.pow(2, -10 * t/1) + 1);
		},
		easeInOutExpo: function (t) {
			if (t==0) return 0;
			if (t==1) return 1;
			if ((t/=1/2) < 1) return 1/2 * Math.pow(2, 10 * (t - 1));
			return 1/2 * (-Math.pow(2, -10 * --t) + 2);
		},
		easeInCirc: function (t) {
			if (t>=1) return t;
			return -1 * (Math.sqrt(1 - (t/=1)*t) - 1);
		},
		easeOutCirc: function (t) {
			return 1 * Math.sqrt(1 - (t=t/1-1)*t);
		},
		easeInOutCirc: function (t) {
			if ((t/=1/2) < 1) return -1/2 * (Math.sqrt(1 - t*t) - 1);
			return 1/2 * (Math.sqrt(1 - (t-=2)*t) + 1);
		},
		easeInElastic: function (t) {
			var s=1.70158; var p=0; var a=1;
			if (t==0) return 0;
			if ((t/=1)==1) return 1;
			if (!p) p=1*.3;
			if (a < Math.abs(1)) { a=1; var s=p/4; }
			else var s = p/(2*Math.PI) * Math.asin(1/a);
			return -(a*Math.pow(2,10*(t-=1)) * Math.sin((t*1-s)*(2*Math.PI)/p));
		},
		easeOutElastic: function (t) {
			var s=1.70158; var p=0; var a=1;
			if (t==0) return 0;
			if ((t/=1)==1) return 1;
			if (!p) p=1*.3;
			if (a < Math.abs(1)) { a=1; var s=p/4; }
			else var s = p/(2*Math.PI) * Math.asin(1/a);
			return a*Math.pow(2,-10*t) * Math.sin((t*1-s)*(2*Math.PI)/p) + 1;
		},
		easeInOutElastic: function (t) {
			var s=1.70158; var p=0; var a=1;
			if (t==0) return 0;
			if ((t/=1/2)==2) return 1;
			if (!p) p=1*(.3*1.5);
			if (a < Math.abs(1)) { a=1; var s=p/4; }
			else var s = p/(2*Math.PI) * Math.asin(1/a);
			if (t < 1) return -.5*(a*Math.pow(2,10*(t-=1)) * Math.sin((t*1-s)*(2*Math.PI)/p));
			return a*Math.pow(2,-10*(t-=1)) * Math.sin((t*1-s)*(2*Math.PI)/p)*.5 + 1;
		},
		easeInBack: function (t) {
			var s = 1.70158;
			return 1*(t/=1)*t*((s+1)*t - s);
		},
		easeOutBack: function (t) {
			var s = 1.70158;
			return 1*((t=t/1-1)*t*((s+1)*t + s) + 1);
		},
		easeInOutBack: function (t) {
			var s = 1.70158;
			if ((t/=1/2) < 1) return 1/2*(t*t*(((s*=(1.525))+1)*t - s));
			return 1/2*((t-=2)*t*(((s*=(1.525))+1)*t + s) + 2);
		},
		easeInBounce: function (t) {
			return 1 - animationOptions.easeOutBounce(1-t);
		},
		easeOutBounce: function (t) {
			if ((t/=1) < (1/2.75)) {
				return 1*(7.5625*t*t);
			} else if (t < (2/2.75)) {
				return 1*(7.5625*(t-=(1.5/2.75))*t + .75);
			} else if (t < (2.5/2.75)) {
				return 1*(7.5625*(t-=(2.25/2.75))*t + .9375);
			} else {
				return 1*(7.5625*(t-=(2.625/2.75))*t + .984375);
			}
		},
		easeInOutBounce: function (t) {
			if (t < 1/2) return animationOptions.easeInBounce(t * 2) * .5;
			return animationOptions.easeOutBounce(t * 2 - 1) * .5 + .5;
		}
	};

	this.tooltips = [],
	defaults = {
		tooltips: {
			background: 'rgba(255,255,255,.7)',
			fontFamily: 'sans-serif',
			fontStyle: 'normal',
			fontColor: 'black',
			fontSize: '12px',
			labelTemplate: '<%=label%>\n<%=name%>: <%=value%>',
			height: 24,
			padding: {
				top: 8,
				right: 8,
				bottom: 8,
				left: 8
			},
			position: 'bottom center',
			offset: {
				left: 0,
				top: 0
			},
			border: {
				width: 1,
				color: 'rgba(0,0,0,.1)',
				radius: 4
			},
			showShadow: false,
			shadow: {
				color: 'rgba(0,0,0,.9)',
				blur: 8,
				offsetX: 0,
				offsetY: 0
			},
			showHighlight: true,
			highlight: {
				stroke: {
					width: 1,
					color: 'rgba(130,130,130,.25)'
				},
				fill: 'rgba(255,255,255,.25)'
			}
		}
	},
	options = options ? mergeChartConfig(defaults, options) : defaults;

	function registerTooltip(areaObj, dat, type)
	{
		chart.tooltips.push(new Tooltip(
			areaObj,
			dat,
			type
		));
	}

	var Tooltip = function (areaObj, dat, type)
	{
		this.areaObj = areaObj;
		this.data = dat;
		this.highlightState = null;
		this.x = null;
		this.y = null;

		this.inRange = function (x, y)
		{
			if (this.areaObj.type)
			{
				switch (this.areaObj.type)
				{
					case 'rect':
						return (x >= this.areaObj.x && x <= this.areaObj.x + this.areaObj.width) &&
							   (y >= this.areaObj.y && y <= this.areaObj.y + this.areaObj.height);
						break;
					case 'circle':
						return ((Math.pow(x - this.areaObj.x, 2) + Math.pow(y - this.areaObj.y, 2)) < Math.pow(this.areaObj.r, 2));
						break;
					case 'shape':
						var poly = this.areaObj.points;
						for (var c = false, i = -1, l = poly.length, j = l - 1; ++i < l; j = i)
							((poly[i].y <= y && y < poly[j].y) || (poly[j].y <= y && y < poly[i].y))
							&& (x < (poly[j].x - poly[i].x) * (y - poly[i].y) / (poly[j].y - poly[i].y) + poly[i].x)
							&& (c = !c);
						return c;
						break;
				}
			}
		};

		this.render = function (x, y)
		{
			cx.shadowColor = undefined;
			cx.shadowBlur = 0;
			cx.shadowOffsetX = 0;
			cx.shadowOffsetY = 0;
			if (options.tooltips.showHighlight)
			{
				if (this.highlightState == null)
				{
					cx.putImageData(chart.savedState, 0, 0);
					cx.strokeStyle = options.tooltips.highlight.stroke.color;
					cx.lineWidth = options.tooltips.highlight.stroke.width;
					cx.fillStyle = options.tooltips.highlight.fill;
					switch (this.areaObj.type)
					{
						case 'rect':
							cx.strokeRect(this.areaObj.x, this.areaObj.y, this.areaObj.width, this.areaObj.height);
							cx.fillStyle = options.tooltips.highlight.fill;
							cx.fillRect(this.areaObj.x, this.areaObj.y, this.areaObj.width, this.areaObj.height);
							break;
						case 'circle':
							cx.beginPath();
							cx.arc(this.areaObj.x, this.areaObj.y, this.areaObj.r, 0, 2 * Math.PI, false);
							cx.stroke();
							cx.fill();
							break;
						case 'shape':
							cx.beginPath();
							cx.moveTo(this.areaObj.points[0].x, this.areaObj.points[0].y);
							for (var p in this.areaObj.points)
								cx.lineTo(this.areaObj.points[p].x, this.areaObj.points[p].y);
							cx.stroke();
							cx.fill();
							break;
					}
					this.highlightState = cx.getImageData(0, 0, cx.canvas.width, cx.canvas.height);
				} else
					cx.putImageData(this.highlightState, 0, 0);
			}
			else
				cx.putImageData(chart.savedState, 0, 0);

			cx.font = options.tooltips.fontStyle + ' ' + options.tooltips.fontSize + ' ' + options.tooltips.fontFamily;
			var labs = options.tooltips.labelTemplate.split('\n'), tpl = [], max_width = 0;
			for (var i in labs)
			{
				tpl[i] = tmpl(labs[i], this.data);
				max_width = Math.max(max_width, cx.measureText(tpl[i]).width);
			}

			var posX = x + options.tooltips.offset.left,
				posY = y + options.tooltips.offset.top,
				rectWidth = options.tooltips.padding.left + max_width + options.tooltips.padding.right,
				position = options.tooltips.position.split(' '),
				line_height = options.tooltips.height,
				height;

			// adjust height on fontsize
			if (options.tooltips.fontSize.match(/[0-9]+(.[0-9]+)?px/))
				line_height = parseInt(options.tooltips.fontSize);
			else if (options.tooltips.fontSize.match(/[0-9]+(.[0-9]+)?(\%|em)/))
			{
				function getDefaultFontSize(pa)
				{
					pa = pa || document.body;
					var who = document.createElement('div');
					who.style.cssText = 'display:inline-block; padding:0; line-height:1; position:absolute; visibility:hidden; font-size:1em';
					who.appendChild(document.createTextNode('M')); // The M letter's width usually matches its font's height.

					pa.appendChild(who);
					var fs = who.offsetHeight;
					pa.removeChild(who);
					return fs;
				}
				var size = parseFloat(options.tooltips.fontSize);
				if (options.tooltips.fontSize.match(/[0-9]+(.[0-9]+)?\%/))
					size /= 100;
				line_height = size * getDefaultFontSize(cx.canvas.parentNode);
			}

			line_height += options.tooltips.padding.top;
			height = line_height * tpl.length + options.tooltips.padding.bottom;

			// check relative position
			for (i in position)
			{
				if (i == 0)
				{
					if (position[i] == 'bottom')
						posY -= height;
					else if (position[i] == 'center')
					{
						posY -= height / 2;
						if (position.length == 1)
							posX -= rectWidth / 2;
					}
				}
				if (i == 1)
				{
					if (position[i] == 'right')
						posX -= rectWidth;
					else if (position[i] == 'center')
						posX -= rectWidth / 2;
				}
			}

			// check edges
			if (posX + rectWidth > cx.canvas.width)
				posX -= posX + rectWidth - cx.canvas.width;

			if (posX < 0)
				posX = 0;

			if (posY + height > cx.canvas.height)
				posY -= posY + height - cx.canvas.height;

			if (posY < 0)
				posY = 0;

			cx.fillStyle = options.tooltips.background;
			if (options.tooltips.showShadow)
			{
				cx.shadowColor = options.tooltips.shadow.color;
				cx.shadowBlur = options.tooltips.shadow.blur;
				cx.shadowOffsetX = options.tooltips.shadow.offsetX;
				cx.shadowOffsetY = options.tooltips.shadow.offsetY;
			}
			if (!options.tooltips.border.radius)
			{
				cx.fillRect(posX, posY, rectWidth, height);
				if (options.tooltips.border.width > 0)
				{
					cx.fillStyle = options.tooltips.border.color;
					cx.lineWidth = options.tooltips.border.width;
					cx.strokeRect(posX, posY, rectWidth, height);
				}
			}
			else
			{
				var radius = options.tooltips.border.radius > 12 ? 12 : options.tooltips.border.radius;
				cx.beginPath();
				cx.moveTo(posX + radius, posY);
				cx.lineTo(posX + rectWidth - radius, posY);
				cx.quadraticCurveTo(posX + rectWidth, posY, posX + rectWidth, posY + radius);
				cx.lineTo(posX + rectWidth, posY + height-radius);
				cx.quadraticCurveTo(posX + rectWidth, posY + height, posX + rectWidth-radius, posY + height);
				cx.lineTo(posX + radius, posY + height);
				cx.quadraticCurveTo(posX, posY + height, posX, posY + height-radius);
				cx.lineTo(posX, posY + radius);
				cx.quadraticCurveTo(posX, posY, posX + radius, posY);
				cx.fill();
				if (options.tooltips.border.width > 0)
				{
					cx.strokeStyle = options.tooltips.border.color;
					cx.lineWidth = options.tooltips.border.width;
					cx.stroke();
				}
				cx.closePath();
			}
			cx.fillStyle = options.tooltips.fontColor;
			cx.textAlign = 'center';
			cx.textBaseline = 'middle';
			for (i in tpl)
			{
				cx.fillText(
					tpl[i],
					posX + rectWidth / 2,
					posY + (line_height + options.tooltips.padding.bottom) / 2 + line_height * i
				);
			}
			this.x = x;
			this.y = y;
		}
	};

	// Variables global to the chart
	var width = cx.canvas.width,
		height = cx.canvas.height,
		position = $(cx.canvas).offset(),
		dragStartX, yAxisPosX, xAxisPosY,
		calculatedScale,
		scaleHop, valueHop,
		animPc, data,
		shownTooltips = 0,

		// In order, we'll get pageX (mousedown, mousemove, mouseup), touches[0].pageX (touchstart, touchmove), and changedTouched[0].pageX (touchend). Uh.
		pageXY = function (e, p) { return p in e ? e[p] : (e = e.originalEvent, e.touches[0] ? e.touches[0][p] : (e.changedTouches[0] ? e.changedTouches[0][p] : -1000)); },
		pageX = function (e) { return pageXY(e, 'pageX') - position.left; },
		pageY = function (e) { return pageXY(e, 'pageY') - position.top; },

		render_tooltips = function (e)
		{
			if (!chart.tooltips.length)
				return 0;

			var rendered = 0, i;
			for (i in chart.tooltips)
			{
				if (chart.tooltips[i].inRange(pageX(e), pageY(e)))
				{
					chart.tooltips[i].render(pageX(e), pageY(e));
					rendered++;
				}
			}
			return rendered;
		};

	// High pixel density displays - multiply the size of the canvas height/width by the device pixel ratio, then scale.
	if (window.devicePixelRatio)
	{
		cx.canvas.style.width = width + 'px';
		cx.canvas.style.height = height + 'px';
		cx.canvas.height = height * window.devicePixelRatio;
		cx.canvas.width = width * window.devicePixelRatio;
		cx.scale(window.devicePixelRatio, window.devicePixelRatio);
	}

	this.Line = function (dat, options)
	{
		chart.Line.defaults = {
			scaleOverlay: false,
			scaleOverride: false,
			scaleSteps: null,
			scaleStepWidth: null,
			scaleStartValue: null,
			scaleLineColor: 'rgba(0,0,0,.2)',
			scaleLineWidth: 1,
			scaleShowLabels: true,
			scaleLabel: '<%=value%>',
			scaleFontFamily: 'sans-serif',
			scaleFontSize: 12,
			scaleFontStyle: 'normal',
			scaleFontColor: '#666',
			scaleShowGridLines: true,
			scaleGridLineColor: 'rgba(0,0,0,.05)',
			scaleGridLineWidth: 1,
			bezierCurve: true,
			pointDot: true,
			pointDotRadius: 4,
			pointDotStrokeWidth: 2,
			datasetStroke: true,
			datasetStrokeWidth: 2,
			datasetFill: true,
			animation: true,
			animationSteps: 60,
			animationEasing: 'easeOutQuart',
			onAnimationComplete: null,
			showTooltips: true
		};
		var config = options ? mergeChartConfig(chart.Line.defaults, options) : chart.Line.defaults;
		data = dat;

		return new Line(config);
	};

	var Line = function (config)
	{
		var
			maxSize, labelHeight, scaleHeight,
			valueBounds, labelTemplateString, widestXLabel,
			xAxisLength, rotateLabels = 0;

		// Mark tooltips as dirty, in case we're re-calling this.
		chart.tooltips = [];
		calculateDrawingSizes();

		valueBounds = getValueBounds();
		// Check and set the scale
		labelTemplateString = config.scaleShowLabels ? config.scaleLabel : '';
		if (!config.scaleOverride)
			calculatedScale = calculateScale(scaleHeight, valueBounds.maxSteps, valueBounds.minSteps, valueBounds.maxValue, valueBounds.minValue, labelTemplateString);
		else
		{
			calculatedScale = {
				steps: config.scaleSteps,
				stepValue: config.scaleStepWidth,
				graphMin: config.scaleStartValue,
				labels: []
			};
			populateLabels(labelTemplateString, calculatedScale.labels, calculatedScale.steps, config.scaleStartValue, config.scaleStepWidth);
		}

		scaleHop = Math.floor(scaleHeight / calculatedScale.steps);
		calculateXAxisSize();
		animationLoop(config, drawScale, drawLines, cx);

		function drawLines(animPc)
		{
			for (var i = 0, j, k; i < data.datasets.length; i++)
			{
				cx.strokeStyle = data.datasets[i].strokeColor;
				cx.lineWidth = config.datasetStrokeWidth;
				cx.beginPath();
				cx.moveTo(yAxisPosX, yPos(i, 0));

				for (j = 1; j < data.datasets[i].data.length; j++)
				{
					if (config.bezierCurve)
						cx.bezierCurveTo(xPos(j - .5), yPos(i, j - 1), xPos(j - .5), yPos(i, j), xPos(j), yPos(i, j));
					else
						cx.lineTo(xPos(j), yPos(i, j));
				}
				var pointRadius = config.pointDot ? config.pointDotRadius + config.pointDotStrokeWidth : 10;
				for (j = 0; j < data.datasets[i].data.length; j++)
				{
					if (animPc >= 1 && config.showTooltips)
					{
						// register tooltips
						registerTooltip(
							{
								type: 'circle',
								x: xPos(j),
								y: yPos(i, j),
								r: pointRadius
							},
							{
								name: data.datasets[i].name ? data.datasets[i].name : '',
								label: data.longlabels ? data.longlabels[j] : data.labels[j],
								value: data.datasets[i].data[j]
							},
							'Line'
						);
					}
				}
				cx.stroke();
				if (config.datasetFill)
				{
					cx.lineTo(xPos(data.datasets[i].data.length - 1), xAxisPosY);
					cx.lineTo(yAxisPosX, xAxisPosY);
					cx.closePath();
					cx.fillStyle = data.datasets[i].fillColor;
					cx.fill();
				}
				else
					cx.closePath();

				if (config.pointDot)
				{
					cx.fillStyle = data.datasets[i].pointColor;
					cx.strokeStyle = data.datasets[i].pointStrokeColor;
					cx.lineWidth = config.pointDotStrokeWidth;
					for (k = 0; k < data.datasets[i].data.length; k++)
					{
						cx.beginPath();
						cx.arc(
							xPos(k),
							yPos(i, k),
							config.pointDotRadius,
							0,
							Math.PI * 2,
							true
						);
						cx.fill();
						cx.stroke();
					}
				}
			}
		}
		function drawScale()
		{
			// X axis line
			cx.lineWidth = config.scaleLineWidth;
			cx.strokeStyle = config.scaleLineColor;
			cx.beginPath();
			cx.moveTo(width - widestXLabel / 2 + 5, xAxisPosY);
			cx.lineTo(width - widestXLabel / 2 - xAxisLength - 5, xAxisPosY);
			cx.stroke();

			if (rotateLabels > 0)
			{
				cx.save();
				cx.textAlign = 'right';
			}
			else
				cx.textAlign = 'center';

			cx.fillStyle = config.scaleFontColor;
			cx.font = config.scaleFontStyle + ' ' + config.scaleFontSize + 'px ' + config.scaleFontFamily;
			for (var i = 0, j = data.labels.length, bold = false, label; i < j; i++)
			{
				if (!data.labels[i])
					continue;
				cx.save();
				label = data.labels[i] + '';
				if (label[0] == '*')
				{
					bold = true;
					label = label.slice(1);
					cx.font = 'bold ' + config.scaleFontSize + 'px ' + config.scaleFontFamily;
				}
				if (rotateLabels > 0)
				{
					cx.translate(yAxisPosX + i * valueHop, xAxisPosY + config.scaleFontSize);
					cx.rotate(-rotateLabels);
					cx.fillText(label, 0, 0);
					cx.restore();
				}
				else
					cx.fillText(label, yAxisPosX + i * valueHop, xAxisPosY + config.scaleFontSize + 3);

				// Check i isn't 0, so we don't go over the Y axis twice.
				if (config.scaleShowGridLines && i > 0)
				{
					cx.beginPath();
					cx.moveTo(yAxisPosX + i * valueHop, xAxisPosY + 4);
					cx.lineTo(yAxisPosX + i * valueHop, xAxisPosY);
					cx.stroke();
					cx.lineWidth = config.scaleGridLineWidth;
					cx.strokeStyle = config.scaleGridLineColor;
					cx.lineTo(yAxisPosX + i * valueHop, 5);
					cx.stroke();
				}
				if (bold)
				{
					bold = false;
					cx.font = config.scaleFontStyle + ' ' + config.scaleFontSize + 'px ' + config.scaleFontFamily;
				}
			}

			// Y axis
			cx.lineWidth = config.scaleLineWidth;
			cx.strokeStyle = config.scaleLineColor;
			cx.beginPath();
			cx.moveTo(yAxisPosX, xAxisPosY + 5);
			cx.lineTo(yAxisPosX, 5);
			cx.stroke();

			cx.textAlign = 'right';
			cx.textBaseline = 'middle';
			for (j = 0; j < calculatedScale.steps; j++)
			{
				cx.beginPath();
				cx.moveTo(yAxisPosX - 3, xAxisPosY - ((j + 1) * scaleHop));
				if (config.scaleShowGridLines)
				{
					cx.lineWidth = config.scaleGridLineWidth;
					cx.strokeStyle = config.scaleGridLineColor;
					cx.lineTo(yAxisPosX + xAxisLength + 5, xAxisPosY - ((j + 1) * scaleHop));
				}
				cx.stroke();

				if (config.scaleShowLabels)
					cx.fillText(calculatedScale.labels[j], yAxisPosX - 8, xAxisPosY - ((j + 1) * scaleHop));
			}
		}
		function calculateXAxisSize()
		{
			var longestText = 1;
			// if we are showing the labels
			if (config.scaleShowLabels)
			{
				cx.font = config.scaleFontStyle + ' ' + config.scaleFontSize + 'px ' + config.scaleFontFamily;
				for (var i = 0; i < calculatedScale.labels.length; i++)
				{
					var measuredText = cx.measureText(calculatedScale.labels[i]).width;
					longestText = measuredText > longestText ? measuredText : longestText;
				}
				// Add a little extra padding from the y axis
				longestText += 10;
			}
			xAxisLength = width - longestText - widestXLabel;
			valueHop = Math.floor(xAxisLength / (data.labels.length - 1));

			yAxisPosX = width - widestXLabel / 2 - xAxisLength;
			xAxisPosY = scaleHeight + config.scaleFontSize / 2;
		}
		function calculateDrawingSizes()
		{
			maxSize = height;

			// Need to check the X axis first - measure the length of each text metric, and figure out if we need to rotate by 45 degrees.
			cx.font = config.scaleFontStyle + ' ' + config.scaleFontSize + 'px ' + config.scaleFontFamily;
			widestXLabel = 1;
			for (var i = 0; i < data.labels.length; i++)
			{
				var textLength = cx.measureText(data.labels[i]).width;
				// If the text length is longer - make that equal to longest text!
				widestXLabel = textLength > widestXLabel ? textLength : widestXLabel;
			}
			if (width / data.labels.length < widestXLabel)
			{
				var
					skip = 0,
					deg = Math.PI / 180,
					awkwardRotation = 45 * deg,
					maxRotation = 90 * deg,
					// widestXLabel +20% helps against labels overflowing each other through their heights.
					ideal_ratio = width / data.labels.length / (widestXLabel * 1.2);

				rotateLabels = 0;
				while (Math.cos(rotateLabels) > ideal_ratio && rotateLabels < maxRotation)
				{
					rotateLabels += deg;
					// If it's getting awkward, reset and try skipping half the labels. If it still doesn't work, give up.
					if (rotateLabels > awkwardRotation && skip < 2)
					{
						skip++;
						rotateLabels = 0;
						ideal_ratio *= 2;
					}
				}
				rotateLabels = Math.min(maxRotation, rotateLabels);
				// Increase maxSize by the rotated label width.
				maxSize -= Math.sin(rotateLabels) * widestXLabel;
				var bold_next = false;
				if (skip)
				{
					for (i in data.labels)
					{
						if (i % 2 || (skip > 1 && i % 4))
						{
							if (data.labels[i][0] == '*')
								bold_next = true;
							delete data.labels[i];
						}
						else if (bold_next)
						{
							if (data.labels[i][0] != '*')
								data.labels[i] = '*' + data.labels[i];
							bold_next = false;
						}
					}
				}
			}

			// Set 5 pixels greater than the font size to allow for a little padding from the X axis.
			// Then get the area above we can safely draw on.
			maxSize -= config.scaleFontSize + 5;
			labelHeight = config.scaleFontSize;
			maxSize -= labelHeight;
			scaleHeight = maxSize;
		}
		function getValueBounds()
		{
			var	upperValue = Number.MIN_VALUE,
				lowerValue = Number.MAX_VALUE,
				i, j, k, maxSteps, minSteps;

			for (i = 0; i < data.datasets.length; i++)
			{
				for (j = 0, k = data.datasets[i].data.length; j < k; j++)
				{
					if (data.datasets[i].data[j] > upperValue)
						upperValue = data.datasets[i].data[j];
					if (data.datasets[i].data[j] < lowerValue)
						lowerValue = data.datasets[i].data[j];
				}
			};

			maxSteps = Math.floor(scaleHeight / labelHeight);
			minSteps = Math.floor(scaleHeight / labelHeight * .4);

			return {
				maxValue: Math.max(5, upperValue),
				minValue: lowerValue,
				maxSteps: maxSteps,
				minSteps: minSteps
			};
		}
	};

	function goToRange(low, high)
	{
		// Determine days/months/etc. matching min and max pointer positions.
		for (var i = 0, j = data.datasets[0].data.length, start = null; i < j; i++)
		{
			var current = xPos(i);
			if (start === null && current > low)
				start = i;
			if (current >= high)
			{
				zoom_hook.call(this, data.range[start], data.range[i] || '');
				return;
			}
		}
		zoom_hook.call(this, data.range[start] || data.range[i - 1] || '', data.range[i - 1] || '');
	}

	function initActivity()
	{
		// Now that the animation is finished, put the chart into cache, once and for all.
		chart.savedState = cx.getImageData(0, 0, cx.canvas.width, cx.canvas.height);

		$(cx.canvas)
			.on(is_touch ? 'touchstart' : 'mousedown', function (e)
			{
				// Catch button clicks, but only if they're not on a chart dot.
				position = $(cx.canvas).offset();
				if (!render_tooltips(e) && !!data.range)
					dragStartX = pageX(e);
			})
			.on(is_touch ? 'touchmove' : 'mousemove', function (e)
			{
				// Render the tooltips. If none of them are shown, we'll restore
				// the chart as needed after showing the (potential) selection box.
				position = $(cx.canvas).offset();
				var rendered = render_tooltips(e);
				if (dragStartX !== null && Math.abs(pageX(e) - dragStartX) > 10)
				{
					if (!rendered && chart.savedState !== null)
						cx.putImageData(chart.savedState, 0, 0);
					// Render the zoom selection.
					cx.beginPath();
					cx.fillStyle = 'rgba(0,0,0,.1)';
					cx.strokeStyle = 'rgba(0,0,0,.8)';
					cx.lineWidth = 2;
					cx.rect(dragStartX, 0, pageX(e) - dragStartX, xAxisPosY);
					cx.fill();
				}
				else if (!rendered && shownTooltips && chart.savedState !== null && !is_touch)
					cx.putImageData(chart.savedState, 0, 0);
				shownTooltips = rendered;
				e.preventDefault();
			})
			.on(is_touch ? 'touchend' : 'mouseup', function (e)
			{
				var dragEndX = pageX(e);
				if (dragStartX !== null && Math.abs(dragEndX - dragStartX) > 10)
					goToRange(Math.min(dragStartX, dragEndX), Math.max(dragStartX, dragEndX));

				if (chart.savedState !== null && (!is_touch || dragStartX !== null))
					cx.putImageData(chart.savedState, 0, 0);
				dragStartX = null;
			})
			.on('mouseout', function (e)
			{
				if (chart.savedState !== null)
					cx.putImageData(chart.savedState, 0, 0);
				dragStartX = null;
			});
	}

	function calculateOffset(val, calculatedScale, scaleHop)
	{
		var	outerValue = calculatedScale.steps * calculatedScale.stepValue,
			adjustedValue = val - calculatedScale.graphMin,
			scalingFactor = Math.max(Math.min(adjustedValue / outerValue, 1), 0);

		return (scaleHop * calculatedScale.steps) * scalingFactor;
	}

	function yPos(dataSet, iteration) {
		return xAxisPosY - animPc * (calculateOffset(data.datasets[dataSet].data[iteration], calculatedScale, scaleHop));
	}

	function xPos(iteration) {
		return yAxisPosX + (valueHop * iteration);
	}

	function animationLoop(config, drawScale, drawData)
	{
		var	animFrameAmount = config.animation ? 1 / Math.max(config.animationSteps, 1) : 1,
			easingFunction = animationOptions[config.animationEasing],
			percentAnimComplete = config.animation ? 0 : 1;

		if (typeof drawScale != 'function')
			drawScale = function () {};

		requestAnimFrame(animLoop);

		function animateFrame()
		{
			animPc = config.animation ? Math.max(easingFunction(percentAnimComplete), 0) : 1;
			cx.clearRect(0, 0, width, height);
			if (config.scaleOverlay)
			{
				drawData(animPc);
				drawScale();
			}
			else
			{
				drawScale();
				drawData(animPc);
			}
		}
		function animLoop()
		{
			// We need to check if the animation is incomplete (less than 1), or complete (1).
			percentAnimComplete += animFrameAmount;
			animateFrame();
			// Stop the loop continuing forever
			if (percentAnimComplete <= 1)
				requestAnimFrame(animLoop);
			else
			{
				if (typeof config.onAnimationComplete == 'function')
					config.onAnimationComplete();
				initActivity();
			}
		}
	}

	// Declare global functions to be called within this namespace here.

	// shim layer with setTimeout fallback
	var requestAnimFrame = (function () {
		return window.requestAnimationFrame ||
			window.webkitRequestAnimationFrame ||
			window.mozRequestAnimationFrame ||
			window.oRequestAnimationFrame ||
			window.msRequestAnimationFrame ||
			function (callback) {
				window.setTimeout(callback, 1000 / 60);
			};
	})();

	function calculateScale(drawingHeight, maxSteps, minSteps, maxValue, minValue, labelTemplateString)
	{
		var graphMin, graphMax, graphRange, stepValue, numberOfSteps, valueRange, rangeOrderOfMagnitude, decimalNum;
		valueRange = maxValue - minValue;
		rangeOrderOfMagnitude = Math.floor(Math.log(valueRange) / Math.LN10),
		graphMin = Math.floor(minValue / (1 * Math.pow(10, rangeOrderOfMagnitude))) * Math.pow(10, rangeOrderOfMagnitude);
		graphMax = Math.ceil(maxValue / (1 * Math.pow(10, rangeOrderOfMagnitude))) * Math.pow(10, rangeOrderOfMagnitude);
		graphRange = graphMax - graphMin;
		stepValue = Math.pow(10, rangeOrderOfMagnitude);
		numberOfSteps = Math.round(graphRange / stepValue);

		// A quick fix for things like maxValue being at 101, resulting in a graphMax of 200, which is too high...
		while (graphMax > maxValue * 1.25)
		{
			graphMax /= 1.25;
			graphMin /= 1.25;
			graphRange = graphMax - graphMin;
			numberOfSteps = Math.round(graphRange / stepValue);
		}

		// Compare number of steps to the max and min for that size graph, and add in half steps if need be.
		while (numberOfSteps < minSteps || numberOfSteps > maxSteps)
		{
			if (numberOfSteps < minSteps)
			{
				stepValue /= 2;
				numberOfSteps = Math.round(graphRange / stepValue);
			}
			else
			{
				stepValue *= 2;
				numberOfSteps = Math.round(graphRange / stepValue);
			}
		}

		var labels = [];
		populateLabels(labelTemplateString, labels, numberOfSteps, graphMin, stepValue);

		return {
			steps: numberOfSteps,
			stepValue: stepValue,
			graphMin: graphMin,
			labels: labels
		};
	}

	// Populate an array of all the labels by interpolating the string.
	function populateLabels(labelTemplateString, labels, numberOfSteps, graphMin, stepValue)
	{
		if (labelTemplateString)
			for (var i = 1; i < numberOfSteps + 1; i++)
				labels.push(tmpl(labelTemplateString, {
					value: (graphMin + (stepValue * i)).toFixed((stepValue % 1) ? ('' + stepValue).split('.')[1].length : 0)
				}));
	}

	function mergeChartConfig(defaults, userDefined)
	{
		var returnObj = {}, attrname;
		for (attrname in defaults)
			returnObj[attrname] = defaults[attrname];
		for (attrname in userDefined)
		{
			if (typeof(userDefined[attrname]) == 'object' && defaults[attrname])
				returnObj[attrname] = mergeChartConfig(defaults[attrname], userDefined[attrname]);
			else
				returnObj[attrname] = userDefined[attrname];
		}
		return returnObj;
	}

	// Javascript micro templating by John Resig - source at http://ejohn.org/blog/javascript-micro-templating/
	var cache = {};

	function tmpl(str, dat)
	{
		// Figure out if we're getting a template, or if we need to
		// load the template - and be sure to cache the result.
		var fn = !/\W/.test(str) ?
			cache[str] = cache[str] ||
			tmpl(document.getElementById(str).innerHTML) :

			// Generate a reusable function that will serve as a template
			// generator (and which will be cached).
			new Function("obj",
				"var p=[],print=function () {p.push.apply(p,arguments);};" +

				// Introduce the data as local variables using with() {}
				"with(obj){p.push('" +

				// Convert the template into pure JavaScript
				str
					.replace(/[\r\t\n]/g, " ")
					.split("<%").join("\t")
					.replace(/((^|%>)[^\t]*)'/g, "$1\r")
					.replace(/\t=(.*?)%>/g, "',$1,'")
					.split("\t").join("');")
					.split("%>").join("p.push('")
					.split("\r").join("\\'")

				+ "');}return p.join('');"
			);

		// Provide some basic currying to the user
		return dat ? fn(dat) : fn;
	};
};

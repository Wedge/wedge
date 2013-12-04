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
window.Wraph = function (context, options) {
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
			return -1 *t*(t-2);
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
			fontFamily: "'Arial'",
			fontStyle: "normal",
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
					color: 'rgba(230,230,230,.25)'
				},
				fill: 'rgba(255,255,255,.25)'
			}
		}
	},
	options = options ? mergeChartConfig(defaults, options) : defaults;

	function registerTooltip(ctx,areaObj,data,type) {
		chart.tooltips.push(new Tooltip(
			ctx,
			areaObj,
			data,
			type
		));
	}

	var Tooltip = function (ctx, areaObj, data, type) {
		this.ctx = ctx;
		this.areaObj = areaObj;
		this.data = data;
		this.savedState = null;
		this.highlightState = null;
		this.x = null;
		this.y = null;

		this.inRange = function (x,y) {
			if (this.areaObj.type) {
				switch (this.areaObj.type) {
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

		this.render = function (x,y) {
			this.ctx.shadowColor = undefined;
			this.ctx.shadowBlur = 0;
			this.ctx.shadowOffsetX = 0;
			this.ctx.shadowOffsetY = 0;
			if (this.savedState == null) {
				this.ctx.putImageData(chart.savedState,0,0);
				this.savedState = this.ctx.getImageData(0,0,this.ctx.canvas.width,this.ctx.canvas.height);
			}
			this.ctx.putImageData(this.savedState,0,0);
			if (options.tooltips.showHighlight) {
				if (this.highlightState == null) {
					this.ctx.strokeStyle = options.tooltips.highlight.stroke.color;
					this.ctx.lineWidth = options.tooltips.highlight.stroke.width;
					this.ctx.fillStyle = options.tooltips.highlight.fill;
					switch (this.areaObj.type) {
						case 'rect':
							this.ctx.strokeRect(this.areaObj.x, this.areaObj.y, this.areaObj.width, this.areaObj.height);
							this.ctx.fillStyle = options.tooltips.highlight.fill;
							this.ctx.fillRect(this.areaObj.x, this.areaObj.y, this.areaObj.width, this.areaObj.height);
							break;
						case 'circle':
							this.ctx.beginPath();
							this.ctx.arc(this.areaObj.x, this.areaObj.y, this.areaObj.r, 0, 2*Math.PI, false);
							this.ctx.stroke();
							this.ctx.fill();
							break;
						case 'shape':
							this.ctx.beginPath();
							this.ctx.moveTo(this.areaObj.points[0].x, this.areaObj.points[0].y);
							for (var p in this.areaObj.points) {
								this.ctx.lineTo(this.areaObj.points[p].x, this.areaObj.points[p].y);
							}
							this.ctx.stroke();
							this.ctx.fill();
							break;
					}
					this.highlightState = this.ctx.getImageData(0,0,this.ctx.canvas.width,this.ctx.canvas.height);
				} else {
					this.ctx.putImageData(this.highlightState,0,0);
				}
			}
			this.ctx.font = options.tooltips.fontStyle + " " + options.tooltips.fontSize + " " + options.tooltips.fontFamily;
			var labs = options.tooltips.labelTemplate.split("\n"), tpl = [], max_width = 0;
			for (var i in labs)
			{
				tpl[i] = tmpl(labs[i], this.data);
				max_width = Math.max(max_width, this.ctx.measureText(tpl[i]).width);
			}

			var posX = x+options.tooltips.offset.left,
				posY = y+options.tooltips.offset.top,
				rectWidth = options.tooltips.padding.left+max_width+options.tooltips.padding.right,
				position = options.tooltips.position.split(" "),
				line_height = options.tooltips.height,
				height;

			// adjust height on fontsize
			if (options.tooltips.fontSize.match(/[0-9]+(.[0-9]+)?px/)) {
				line_height = parseInt(options.tooltips.fontSize);
			}
			else if (options.tooltips.fontSize.match(/[0-9]+(.[0-9]+)?(\%|em)/)) {
				function getDefaultFontSize(pa) {
					pa = pa || document.body;
					var who = document.createElement('div');
					who.style.cssText='display:inline-block; padding:0; line-height:1; position:absolute; visibility:hidden; font-size:1em';
					who.appendChild(document.createTextNode('M')); // The M letter's width usually matches its font's height.

					pa.appendChild(who);
					var fs = who.offsetHeight;
					pa.removeChild(who);
					return fs;
				}
				var size = parseFloat(options.tooltips.fontSize);
				if (options.tooltips.fontSize.match(/[0-9]+(.[0-9]+)?\%/)) {
					size /= 100;
				}
				line_height = size * getDefaultFontSize(this.ctx.canvas.parentNode);
			}

			line_height += options.tooltips.padding.top;
			height = line_height * tpl.length +  + options.tooltips.padding.bottom;

			// check relative position
			for (i in position) {
				if (i == 0) {
					if (position[i] == "bottom") {
						posY -= height;
					} else if (position[i] == "center") {
						posY -= height / 2;
						if (position.length == 1) {
							posX -= rectWidth / 2;
						}
					}
				}
				if (i == 1) {
					if (position[i] == "right") {
						posX -= rectWidth;
					} else if (position[i] == "center") {
						posX -= rectWidth / 2;
					}
				}
			}

			// check edges
			if (posX + rectWidth > ctx.canvas.width) {
				posX -= posX + rectWidth - ctx.canvas.width;
			}
			if (posX < 0) {
				posX = 0;
			}
			if (posY + height > ctx.canvas.height) {
				posY -= posY + height - ctx.canvas.height;
			}
			if (posY < 0) {
				posY = 0;
			}
			this.ctx.fillStyle = options.tooltips.background;
			if (options.tooltips.showShadow) {
				this.ctx.shadowColor = options.tooltips.shadow.color;
				this.ctx.shadowBlur = options.tooltips.shadow.blur;
				this.ctx.shadowOffsetX = options.tooltips.shadow.offsetX;
				this.ctx.shadowOffsetY = options.tooltips.shadow.offsetY;
			}
			if (!options.tooltips.border.radius) {
				this.ctx.fillRect(posX, posY, rectWidth, height);
				if (options.tooltips.border.width > 0) {
					this.ctx.fillStyle = options.tooltips.border.color;
					this.ctx.lineWidth = options.tooltips.border.width;
					this.ctx.strokeRect(posX, posY, rectWidth, height);
				}
			} else {
				var radius = options.tooltips.border.radius > 12 ? 12 : options.tooltips.border.radius;
				this.ctx.beginPath();
				this.ctx.moveTo(posX + radius, posY);
				this.ctx.lineTo(posX + rectWidth - radius, posY);
				this.ctx.quadraticCurveTo(posX + rectWidth, posY, posX + rectWidth, posY + radius);
				this.ctx.lineTo(posX + rectWidth, posY + height-radius);
				this.ctx.quadraticCurveTo(posX + rectWidth, posY + height, posX + rectWidth-radius, posY + height);
				this.ctx.lineTo(posX + radius, posY + height);
				this.ctx.quadraticCurveTo(posX, posY + height, posX, posY + height-radius);
				this.ctx.lineTo(posX, posY + radius);
				this.ctx.quadraticCurveTo(posX, posY, posX + radius, posY);
				this.ctx.fill();
				if (options.tooltips.border.width > 0) {
					this.ctx.strokeStyle = options.tooltips.border.color;
					this.ctx.lineWidth = options.tooltips.border.width;
					this.ctx.stroke();
				}
				this.ctx.closePath();
			}
			this.ctx.fillStyle = options.tooltips.fontColor;
			this.ctx.textAlign = 'center';
			this.ctx.textBaseline = 'middle';
			for (i in tpl) {
				this.ctx.fillText(
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
	var width = context.canvas.width,
		height = context.canvas.height;

	this.savedState = null;

	function getPosition(e) {
		var xPosition = 0;
		var yPosition = 0;

		while (e) {
			xPosition += (e.offsetLeft + e.clientLeft);
			yPosition += (e.offsetTop + e.clientTop);
			e = e.offsetParent;
		}
		if (window.pageXOffset > 0 || window.pageYOffset > 0) {
			xPosition -= window.pageXOffset;
			yPosition -= window.pageYOffset;
		} else if (document.documentElement.scrollLeft > 0 || document.documentElement.scrollTop > 0) {
			xPosition -= document.documentElement.scrollLeft;
			yPosition -= document.documentElement.scrollTop;
		}
		return { x: xPosition, y: yPosition };
	}

	function tooltipEventHandler(e) {
		if (chart.tooltips.length > 0) {
			chart.savedState = chart.savedState == null ? context.getImageData(0,0,context.canvas.width,context.canvas.height) : chart.savedState;
			var rendered = 0;
			for (var i in chart.tooltips) {
				var position = getPosition(context.canvas),
					mx = (e.clientX)-position.x,
					my = (e.clientY)-position.y;
				if (chart.tooltips[i].inRange(mx,my)) {
					chart.tooltips[i].render(mx,my);
					rendered++;
				}
			}
			if (rendered == 0) {
				context.putImageData(chart.savedState,0,0);
			}
		}
	}

	if ("touchstart" in window) {
		context.canvas.ontouchstart = function (e) {
			e.clientX = e.targetTouches[0].clientX;
			e.clientY = e.targetTouches[0].clientY;
			tooltipEventHandler(e);
		};
		context.canvas.ontouchmove = function (e) {
			e.clientX = e.targetTouches[0].clientX;
			e.clientY = e.targetTouches[0].clientY;
			tooltipEventHandler(e);
		};
	} else {
		context.canvas.onmousemove = function (e) {
			tooltipEventHandler(e);
		};
	}
	context.canvas.onmouseout = function (e) {
		if (chart.savedState != null) {
			context.putImageData(chart.savedState,0,0);
		}
	};

	// High pixel density displays - multiply the size of the canvas height/width by the device pixel ratio, then scale.
	if (window.devicePixelRatio) {
		context.canvas.style.width = width + "px";
		context.canvas.style.height = height + "px";
		context.canvas.height = height * window.devicePixelRatio;
		context.canvas.width = width * window.devicePixelRatio;
		context.scale(window.devicePixelRatio, window.devicePixelRatio);
	}

	this.Line = function (data,options) {

		chart.Line.defaults = {
			scaleOverlay: false,
			scaleOverride: false,
			scaleSteps: null,
			scaleStepWidth: null,
			scaleStartValue: null,
			scaleLineColor: "rgba(0,0,0,.2)",
			scaleLineWidth: 1,
			scaleShowLabels: true,
			scaleLabel: "<%=value%>",
			scaleFontFamily: "'Arial'",
			scaleFontSize: 12,
			scaleFontStyle: "normal",
			scaleFontColor: "#666",
			scaleShowGridLines: true,
			scaleGridLineColor: "rgba(0,0,0,.05)",
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
			animationEasing: "easeOutQuart",
			onAnimationComplete: null,
			showTooltips: true
		};
		var config = options ? mergeChartConfig(chart.Line.defaults,options) : chart.Line.defaults;

		return new Line(data,config,context);
	};

	var clear = function (c) {
		c.clearRect(0, 0, width, height);
	};

	var Line = function (data,config,ctx) {
		var maxSize, scaleHop, calculatedScale, labelHeight, scaleHeight, valueBounds, labelTemplateString, valueHop, widestXLabel, xAxisLength, yAxisPosX, xAxisPosY, rotateLabels = 0;

		// Mark tooltips as dirty, in case we're re-calling this.
		chart.tooltips = [];
		calculateDrawingSizes();

		valueBounds = getValueBounds();
		// Check and set the scale
		labelTemplateString = config.scaleShowLabels ? config.scaleLabel : "";
		if (!config.scaleOverride) {
			calculatedScale = calculateScale(scaleHeight, valueBounds.maxSteps, valueBounds.minSteps, valueBounds.maxValue, valueBounds.minValue, labelTemplateString);
		}
		else {
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
		animationLoop(config, drawScale, drawLines, ctx);

		function drawLines(animPc) {
			for (var i = 0; i < data.datasets.length; i++) {
				ctx.strokeStyle = data.datasets[i].strokeColor;
				ctx.lineWidth = config.datasetStrokeWidth;
				ctx.beginPath();
				ctx.moveTo(yAxisPosX, xAxisPosY - animPc * (calculateOffset(data.datasets[i].data[0], calculatedScale, scaleHop)));

				for (var j = 1; j < data.datasets[i].data.length; j++) {
					if (config.bezierCurve) {
						ctx.bezierCurveTo(xPos(j - .5), yPos(i, j - 1), xPos(j - .5), yPos(i, j), xPos(j), yPos(i, j));
					}
					else {
						ctx.lineTo(xPos(j), yPos(i, j));
					}
				}
				var pointRadius = config.pointDot ? config.pointDotRadius + config.pointDotStrokeWidth : 10;
				for (var j = 0; j < data.datasets[i].data.length; j++) {
					if (animPc >= 1 && config.showTooltips) {
						// register tooltips
						registerTooltip(
							ctx,
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
				ctx.stroke();
				if (config.datasetFill) {
					ctx.lineTo(yAxisPosX + (valueHop * (data.datasets[i].data.length-1)), xAxisPosY);
					ctx.lineTo(yAxisPosX, xAxisPosY);
					ctx.closePath();
					ctx.fillStyle = data.datasets[i].fillColor;
					ctx.fill();
				}
				else {
					ctx.closePath();
				}
				if (config.pointDot) {
					ctx.fillStyle = data.datasets[i].pointColor;
					ctx.strokeStyle = data.datasets[i].pointStrokeColor;
					ctx.lineWidth = config.pointDotStrokeWidth;
					for (var k = 0; k < data.datasets[i].data.length; k++) {
						ctx.beginPath();
						ctx.arc(
							yAxisPosX + (valueHop *k),
							xAxisPosY - animPc * (calculateOffset(data.datasets[i].data[k],calculatedScale, scaleHop)),
							config.pointDotRadius,
							0,
							Math.PI*2,
							true
						);
						ctx.fill();
						ctx.stroke();
					}
				}
			}

			function yPos(dataSet,iteration) {
				return xAxisPosY - animPc * (calculateOffset(data.datasets[dataSet].data[iteration], calculatedScale, scaleHop));
			}
			function xPos(iteration) {
				return yAxisPosX + (valueHop * iteration);
			}
		}
		function drawScale() {
			// X axis line
			ctx.lineWidth = config.scaleLineWidth;
			ctx.strokeStyle = config.scaleLineColor;
			ctx.beginPath();
			ctx.moveTo(width - widestXLabel / 2 + 5, xAxisPosY);
			ctx.lineTo(width - widestXLabel / 2 - xAxisLength - 5, xAxisPosY);
			ctx.stroke();

			if (rotateLabels > 0) {
				ctx.save();
				ctx.textAlign = "right";
			}
			else {
				ctx.textAlign = "center";
			}
			ctx.fillStyle = config.scaleFontColor;
			for (var i = 0, j = data.labels.length; i < j; i++) {
				if (!data.labels[i])
					continue;
				ctx.save();
				if (rotateLabels > 0) {
					ctx.translate(yAxisPosX + i * valueHop, xAxisPosY + config.scaleFontSize);
					ctx.rotate(-rotateLabels);
					ctx.fillText(data.labels[i], 0, 0);
					ctx.restore();
				}
				else {
					ctx.fillText(data.labels[i], yAxisPosX + i * valueHop, xAxisPosY + config.scaleFontSize + 3);
				}

				// Check i isn't 0, so we don't go over the Y axis twice.
				if (config.scaleShowGridLines && i > 0) {
					ctx.beginPath();
					ctx.moveTo(yAxisPosX + i * valueHop, xAxisPosY + 4);
					ctx.lineTo(yAxisPosX + i * valueHop, xAxisPosY);
					ctx.stroke();
					ctx.lineWidth = config.scaleGridLineWidth;
					ctx.strokeStyle = config.scaleGridLineColor;
					ctx.lineTo(yAxisPosX + i * valueHop, 5);
					ctx.stroke();
				}
			}

			// Y axis
			ctx.lineWidth = config.scaleLineWidth;
			ctx.strokeStyle = config.scaleLineColor;
			ctx.beginPath();
			ctx.moveTo(yAxisPosX, xAxisPosY + 5);
			ctx.lineTo(yAxisPosX, 5);
			ctx.stroke();

			ctx.textAlign = "right";
			ctx.textBaseline = "middle";
			for (j = 0; j < calculatedScale.steps; j++) {
				ctx.beginPath();
				ctx.moveTo(yAxisPosX - 3, xAxisPosY - ((j + 1) * scaleHop));
				if (config.scaleShowGridLines) {
					ctx.lineWidth = config.scaleGridLineWidth;
					ctx.strokeStyle = config.scaleGridLineColor;
					ctx.lineTo(yAxisPosX + xAxisLength + 5, xAxisPosY - ((j + 1) * scaleHop));
				}
				ctx.stroke();

				if (config.scaleShowLabels) {
					ctx.fillText(calculatedScale.labels[j], yAxisPosX - 8, xAxisPosY - ((j + 1) * scaleHop));
				}
			}
		}
		function calculateXAxisSize() {
			var longestText = 1;
			// if we are showing the labels
			if (config.scaleShowLabels) {
				ctx.font = config.scaleFontStyle + " " + config.scaleFontSize + "px " + config.scaleFontFamily;
				for (var i=0; i<calculatedScale.labels.length; i++) {
					var measuredText = ctx.measureText(calculatedScale.labels[i]).width;
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
		function calculateDrawingSizes() {
			maxSize = height;

			// Need to check the X axis first - measure the length of each text metric, and figure out if we need to rotate by 45 degrees.
			ctx.font = config.scaleFontStyle + " " + config.scaleFontSize+"px " + config.scaleFontFamily;
			widestXLabel = 1;
			for (var i = 0; i < data.labels.length; i++) {
				var textLength = ctx.measureText(data.labels[i]).width;
				// If the text length is longer - make that equal to longest text!
				widestXLabel = textLength > widestXLabel ? textLength : widestXLabel;
			}
			if (width / data.labels.length < widestXLabel) {
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
				if (skip)
					for (i in data.labels)
						if (i % 2 || (skip > 1 && i % 4))
							delete data.labels[i];
			}

			// Set 5 pixels greater than the font size to allow for a little padding from the X axis.
			// Then get the area above we can safely draw on.
			maxSize -= config.scaleFontSize + 5;
			labelHeight = config.scaleFontSize;
			maxSize -= labelHeight;
			scaleHeight = maxSize;
		}
		function getValueBounds() {
			var upperValue = Number.MIN_VALUE;
			var lowerValue = Number.MAX_VALUE;
			for (var i = 0; i < data.datasets.length; i++) {
				for (var j = 0, k = data.datasets[i].data.length; j < k; j++) {
					if (data.datasets[i].data[j] > upperValue) { upperValue = data.datasets[i].data[j] };
					if (data.datasets[i].data[j] < lowerValue) { lowerValue = data.datasets[i].data[j] };
				}
			};

			upperValue = Math.max(10, upperValue);
			var maxSteps = Math.floor((scaleHeight / (labelHeight * .66)));
			var minSteps = Math.floor((scaleHeight / labelHeight * .5));

			return {
				maxValue: upperValue,
				minValue: lowerValue,
				maxSteps: maxSteps,
				minSteps: minSteps
			};
		}
	};

	function calculateOffset(val,calculatedScale,scaleHop) {
		var outerValue = calculatedScale.steps * calculatedScale.stepValue;
		var adjustedValue = val - calculatedScale.graphMin;
		var scalingFactor = CapValue(adjustedValue/outerValue,1,0);
		return (scaleHop * calculatedScale.steps) * scalingFactor;
	}

	function animationLoop(config,drawScale,drawData,ctx) {
		var animFrameAmount = config.animation ? 1 / CapValue(config.animationSteps,Number.MAX_VALUE,1) : 1,
			easingFunction = animationOptions[config.animationEasing],
			percentAnimComplete = config.animation ? 0 : 1;

		if (typeof drawScale !== "function") drawScale = function () {};

		requestAnimFrame(animLoop);

		function animateFrame() {
			var easeAdjustedAnimationPercent = config.animation ? CapValue(easingFunction(percentAnimComplete),null,0) : 1;
			clear(ctx);
			if (config.scaleOverlay) {
				drawData(easeAdjustedAnimationPercent);
				drawScale();
			} else {
				drawScale();
				drawData(easeAdjustedAnimationPercent);
			}
		}
		function animLoop() {
			// We need to check if the animation is incomplete (less than 1), or complete (1).
			percentAnimComplete += animFrameAmount;
			animateFrame();
			// Stop the loop continuing forever
			if (percentAnimComplete <= 1) {
				requestAnimFrame(animLoop);
			}
			else {
				// Mark the rendered chart as dirty.
				chart.savedState = null;
				if (typeof config.onAnimationComplete == "function") config.onAnimationComplete();
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

	function calculateScale(drawingHeight, maxSteps, minSteps, maxValue, minValue, labelTemplateString) {
		var graphMin, graphMax, graphRange, stepValue, numberOfSteps, valueRange, rangeOrderOfMagnitude, decimalNum;
		valueRange = maxValue - minValue;
		rangeOrderOfMagnitude = calculateOrderOfMagnitude(valueRange);
		graphMin = Math.floor(minValue / (1 * Math.pow(10, rangeOrderOfMagnitude))) * Math.pow(10, rangeOrderOfMagnitude);
		graphMax = Math.ceil(maxValue / (1 * Math.pow(10, rangeOrderOfMagnitude))) * Math.pow(10, rangeOrderOfMagnitude);
		graphRange = graphMax - graphMin;
		stepValue = Math.pow(10, rangeOrderOfMagnitude);
		numberOfSteps = Math.round(graphRange / stepValue);

		// Compare number of steps to the max and min for that size graph, and add in half steps if need be.
		while (numberOfSteps < minSteps || numberOfSteps > maxSteps) {
			if (numberOfSteps < minSteps) {
				stepValue /= 2;
				numberOfSteps = Math.round(graphRange / stepValue);
			}
			else {
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

		function calculateOrderOfMagnitude(val) {
			return Math.floor(Math.log(val) / Math.LN10);
		}
	}

    // Populate an array of all the labels by interpolating the string.
    function populateLabels(labelTemplateString, labels, numberOfSteps, graphMin, stepValue) {
        if (labelTemplateString) {
            // Fix floating point errors by setting toFixed on the same decimal as the stepValue.
            for (var i = 1; i < numberOfSteps + 1; i++) {
                labels.push(tmpl(labelTemplateString, { value: (graphMin + (stepValue * i)).toFixed(getDecimalPlaces(stepValue)) }));
            }
        }
    }

	// Is a number function
	function isNumber(n) {
		return !isNaN(parseFloat(n)) && isFinite(n);
	}
	// Apply cap a value at a high or low number
	function CapValue(valueToCap, maxValue, minValue) {
		if (isNumber(maxValue)) {
			if (valueToCap > maxValue) {
				return maxValue;
			}
		}
		if (isNumber(minValue)) {
			if (valueToCap < minValue) {
				return minValue;
			}
		}
		return valueToCap;
	}
	function getDecimalPlaces(num) {
		var numberOfDecimalPlaces;
		if (num % 1 != 0) {
			return num.toString().split(".")[1].length
		}
		else {
			return 0;
		}
	}

	function mergeChartConfig(defaults,userDefined) {
		var returnObj = {};
		for (var attrname in defaults) { returnObj[attrname] = defaults[attrname]; }
		for (var attrname in userDefined) {
			if (typeof(userDefined[attrname]) === "object" && defaults[attrname]) {
				returnObj[attrname] = mergeChartConfig(defaults[attrname], userDefined[attrname]);
			} else {
				returnObj[attrname] = userDefined[attrname];
			}
		}
		return returnObj;
	}

	// Javascript micro templating by John Resig - source at http://ejohn.org/blog/javascript-micro-templating/
	var cache = {};

	function tmpl(str, data) {
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
		return data ? fn(data) : fn;
	};
};

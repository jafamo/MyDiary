(function () {
  var MONTHS = ["ene", "feb", "mar", "abr", "may", "jun", "jul", "ago", "sep", "oct", "nov", "dic"];

  function parseData(id) {
    var el = document.getElementById(id);
    if (!el) {
      return [];
    }
    return JSON.parse(el.textContent);
  }

  function fmtShort(dateStr) {
    var parts = dateStr.split("-");
    return parseInt(parts[2], 10) + " " + MONTHS[parseInt(parts[1], 10) - 1];
  }
  function fmtLong(dateStr) {
    var parts = dateStr.split("-");
    return parseInt(parts[2], 10) + " " + MONTHS[parseInt(parts[1], 10) - 1] + " " + parts[0];
  }

  var data = parseData("chart-data");
  var remindersData = parseData("chart-data-reminders");
  if (data.length === 0) {
    return;
  }

  var W = 640, H = 220, padL = 32, padR = 12, padT = 16, padB = 28;
  var plotW = W - padL - padR, plotH = H - padT - padB;
  var n = data.length;

  var dataMax = Math.max.apply(null, data.map(function (d) { return d.value; }));
  var remindersMax = remindersData.length
    ? Math.max.apply(null, remindersData.map(function (d) { return d.value; }))
    : 0;
  var niceMax = Math.max(4, Math.ceil((Math.max(dataMax, remindersMax) + 1) / 2) * 2);

  function xFor(i) { return padL + (n === 1 ? 0 : (i / (n - 1)) * plotW); }
  function yFor(v) { return padT + plotH - (v / niceMax) * plotH; }

  var elLine = document.getElementById("line-path");
  var elArea = document.getElementById("area-path");
  var elLineReminders = document.getElementById("line-path-reminders");
  var elEndpointDot = document.getElementById("endpoint-dot");
  var elEndpointLabel = document.getElementById("endpoint-label");
  var elEndpointDotReminders = document.getElementById("endpoint-dot-reminders");
  var elEndpointLabelReminders = document.getElementById("endpoint-label-reminders");
  var elXLabels = document.getElementById("x-axis-labels");
  var elAxisMax = document.getElementById("axis-max");
  var elAxisMid = document.getElementById("axis-mid");
  var elHit = document.getElementById("hit-area");
  var elCrosshair = document.getElementById("crosshair");
  var elHoverDot = document.getElementById("hover-dot");
  var elHoverDotReminders = document.getElementById("hover-dot-reminders");
  var elTooltip = document.getElementById("chart-tooltip");
  var elTooltipDate = document.getElementById("tooltip-date");
  var elTooltipValue = document.getElementById("tooltip-value");
  var elTooltipValueReminders = document.getElementById("tooltip-value-reminders");
  var elTableBody = document.getElementById("chart-table-body");
  var elSvg = document.getElementById("line-chart");

  if (!elSvg) {
    return;
  }

  elLine.setAttribute("d", "M " + data.map(function (d, i) { return xFor(i) + " " + yFor(d.value); }).join(" L "));
  elArea.setAttribute("d",
    "M " + xFor(0) + " " + (padT + plotH) +
    " L " + data.map(function (d, i) { return xFor(i) + " " + yFor(d.value); }).join(" L ") +
    " L " + xFor(n - 1) + " " + (padT + plotH) + " Z"
  );

  if (remindersData.length) {
    elLineReminders.setAttribute("d", "M " + remindersData.map(function (d, i) { return xFor(i) + " " + yFor(d.value); }).join(" L "));
  }

  elAxisMax.textContent = niceMax;
  elAxisMid.textContent = Math.round(niceMax / 2);

  var last = data[n - 1];
  elEndpointDot.setAttribute("cx", xFor(n - 1));
  elEndpointDot.setAttribute("cy", yFor(last.value));
  elEndpointLabel.setAttribute("x", xFor(n - 1) - 10);
  elEndpointLabel.setAttribute("y", yFor(last.value) - 10);
  elEndpointLabel.setAttribute("text-anchor", "end");
  elEndpointLabel.textContent = last.value;

  if (remindersData.length) {
    var lastReminders = remindersData[n - 1];
    elEndpointDotReminders.setAttribute("cx", xFor(n - 1));
    elEndpointDotReminders.setAttribute("cy", yFor(lastReminders.value));
    elEndpointLabelReminders.setAttribute("x", xFor(n - 1) - 10);
    elEndpointLabelReminders.setAttribute("y", yFor(lastReminders.value) + 16);
    elEndpointLabelReminders.setAttribute("text-anchor", "end");
    elEndpointLabelReminders.textContent = lastReminders.value;
  }

  var idxs = [0, Math.round((n - 1) * 0.25), Math.round((n - 1) * 0.5), Math.round((n - 1) * 0.75), n - 1];
  idxs = idxs.filter(function (v, i) { return idxs.indexOf(v) === i; });
  idxs.forEach(function (i) {
    var t = document.createElementNS("http://www.w3.org/2000/svg", "text");
    t.setAttribute("class", "axis-label");
    t.setAttribute("x", xFor(i));
    t.setAttribute("y", H - 6);
    t.setAttribute("text-anchor", i === 0 ? "start" : (i === n - 1 ? "end" : "middle"));
    t.textContent = fmtShort(data[i].date);
    elXLabels.appendChild(t);
  });

  for (var i = data.length - 1; i >= 0; i--) {
    var d = data[i];
    var tr = document.createElement("tr");
    var tdDate = document.createElement("td");
    tdDate.textContent = fmtLong(d.date);
    var tdVal = document.createElement("td");
    tdVal.textContent = String(d.value);
    var tdReminders = document.createElement("td");
    tdReminders.textContent = remindersData.length ? String(remindersData[i].value) : "0";
    tr.appendChild(tdDate);
    tr.appendChild(tdVal);
    tr.appendChild(tdReminders);
    elTableBody.appendChild(tr);
  }

  var tableToggle = document.getElementById("table-toggle");
  if (tableToggle) {
    tableToggle.addEventListener("click", function () {
      var table = document.getElementById("chart-table");
      var isVisible = table.classList.toggle("is-visible");
      this.setAttribute("aria-pressed", String(isVisible));
      this.textContent = isVisible ? "Ver como gráfico" : "Ver como tabla";
      elSvg.style.display = isVisible ? "none" : "block";
    });
  }

  elHit.addEventListener("pointermove", function (e) {
    var rect = elSvg.getBoundingClientRect();
    var fracX = (e.clientX - rect.left) / rect.width;
    var svgX = fracX * W;
    var i = Math.round(((svgX - padL) / plotW) * (n - 1));
    i = Math.max(0, Math.min(n - 1, i));

    var d = data[i];
    var x = xFor(i);
    var y = yFor(d.value);

    elCrosshair.setAttribute("x1", x);
    elCrosshair.setAttribute("x2", x);
    elCrosshair.style.opacity = 1;
    elHoverDot.setAttribute("cx", x);
    elHoverDot.setAttribute("cy", y);
    elHoverDot.style.opacity = 1;

    if (remindersData.length) {
      var dr = remindersData[i];
      elHoverDotReminders.setAttribute("cx", x);
      elHoverDotReminders.setAttribute("cy", yFor(dr.value));
      elHoverDotReminders.style.opacity = 1;
      elTooltipValueReminders.textContent = dr.value;
    }

    var fracLeft = (x / W) * rect.width;
    elTooltip.style.left = fracLeft + "px";
    elTooltip.classList.add("is-visible");
    elTooltipDate.textContent = fmtLong(d.date);
    elTooltipValue.textContent = d.value;
  });
  elHit.addEventListener("pointerleave", function () {
    elCrosshair.style.opacity = 0;
    elHoverDot.style.opacity = 0;
    elHoverDotReminders.style.opacity = 0;
    elTooltip.classList.remove("is-visible");
  });
})();

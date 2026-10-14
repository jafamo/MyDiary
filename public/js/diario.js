(function () {
  // Mientras el resumen de hoy se genera, consulta su estado y recarga Diario al terminar.
  var status = document.querySelector("[data-summary-generation-status]");
  if (!status || !window.fetch) {
    return;
  }

  var POLL_MS = 5000;
  var IN_PROGRESS = ["QUEUED", "RUNNING"];
  var url = status.getAttribute("data-status-url");

  function poll() {
    fetch(url, { headers: { Accept: "application/json" }, credentials: "same-origin" })
      .then(function (response) {
        return response.ok ? response.json() : null;
      })
      .then(function (data) {
        if (data && IN_PROGRESS.indexOf(data.status) === -1) {
          window.location.reload();
          return;
        }
        window.setTimeout(poll, POLL_MS);
      })
      .catch(function () {
        window.setTimeout(poll, POLL_MS);
      });
  }

  window.setTimeout(poll, POLL_MS);
})();

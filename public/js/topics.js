(function () {
  var table = document.getElementById("topics-table");
  if (!table) {
    return;
  }

  var tbody = table.tBodies[0];
  var rows = Array.prototype.slice.call(tbody.rows);
  var search = document.getElementById("topics-search");
  var meta = document.getElementById("topics-meta");
  var emptyMsg = document.getElementById("topics-empty-filter");
  var pager = document.getElementById("topics-pager");
  var range = document.getElementById("topics-range");
  var pages = document.getElementById("topics-pages");
  var sizeSelect = document.getElementById("topics-page-size");
  var dest = document.getElementById("topic-merge-destination");
  var bar = document.getElementById("topic-merge-bar");
  var countEl = document.getElementById("topic-merge-count");
  var hiddenEl = document.getElementById("topic-merge-hidden");
  var clearBtn = document.getElementById("topic-merge-clear");
  var submit = document.getElementById("topic-merge-submit");

  var MIN_PAGE_SIZE = 25;
  // Mismo orden por defecto que el servidor: resúmenes descendente
  var state = { query: "", key: "count", dir: "desc", page: 1, size: MIN_PAGE_SIZE };
  // true cuando el usuario elige destino a mano: deja de autoseleccionarse el de más resúmenes
  var destPickedByUser = false;

  function norm(s) {
    return s.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");
  }

  function checkbox(tr) {
    return tr.querySelector("input[type=checkbox]");
  }

  function byName(a, b) {
    return a.dataset.name.localeCompare(b.dataset.name, "es", { sensitivity: "base" });
  }

  function compare(a, b) {
    var r;
    if (state.key === "name") {
      r = byName(a, b);
    } else if (state.key === "count") {
      r = parseInt(a.dataset.count, 10) - parseInt(b.dataset.count, 10);
    } else {
      // Los temas sin uso van siempre al final, en cualquier sentido
      var la = a.dataset.lastUsed, lb = b.dataset.lastUsed;
      if (!la && !lb) {
        r = 0;
      } else if (!la) {
        return 1;
      } else if (!lb) {
        return -1;
      } else {
        r = la < lb ? -1 : (la > lb ? 1 : 0);
      }
    }
    if (r === 0) {
      return byName(a, b);
    }
    return state.dir === "asc" ? r : -r;
  }

  function pageList(current, total) {
    var list = [], i;
    if (total <= 7) {
      for (i = 1; i <= total; i++) {
        list.push(i);
      }
      return list;
    }
    var from = Math.max(2, current - 1), to = Math.min(total - 1, current + 1);
    if (current <= 3) {
      to = 4;
    }
    if (current >= total - 2) {
      from = total - 3;
    }
    list.push(1);
    if (from > 2) {
      list.push("…");
    }
    for (i = from; i <= to; i++) {
      list.push(i);
    }
    if (to < total - 1) {
      list.push("…");
    }
    list.push(total);
    return list;
  }

  function pageButton(html, page, opts) {
    var b = document.createElement("button");
    b.type = "button";
    b.className = "pager-btn";
    b.innerHTML = html;
    if (opts.current) {
      b.setAttribute("aria-current", "page");
    }
    if (opts.disabled) {
      b.disabled = true;
    }
    if (opts.label) {
      b.setAttribute("aria-label", opts.label);
    }
    b.addEventListener("click", function () {
      state.page = page;
      render();
    });
    return b;
  }

  function renderPager(total, totalPages, start, shown) {
    pager.hidden = total <= MIN_PAGE_SIZE;
    range.textContent = total === 0 ? "0 de 0" : (start + 1) + "–" + (start + shown) + " de " + total;
    pages.innerHTML = "";
    if (totalPages <= 1) {
      return;
    }
    pages.appendChild(pageButton('<svg class="icon icon--sm"><use href="#i-chevron-left"></use></svg>', state.page - 1, { disabled: state.page === 1, label: "Página anterior" }));
    pageList(state.page, totalPages).forEach(function (p) {
      if (p === "…") {
        var gap = document.createElement("span");
        gap.className = "pager-gap";
        gap.textContent = "…";
        pages.appendChild(gap);
      } else {
        pages.appendChild(pageButton(String(p), p, { current: p === state.page }));
      }
    });
    pages.appendChild(pageButton('<svg class="icon icon--sm"><use href="#i-chevron-right"></use></svg>', state.page + 1, { disabled: state.page === totalPages, label: "Página siguiente" }));
  }

  function renderSortHeaders() {
    table.querySelectorAll("th[data-sort-key]").forEach(function (th) {
      var arrow = th.querySelector(".sort-btn__arrow");
      if (th.dataset.sortKey === state.key) {
        th.setAttribute("aria-sort", state.dir === "asc" ? "ascending" : "descending");
        arrow.textContent = state.dir === "asc" ? "↑" : "↓";
      } else {
        th.removeAttribute("aria-sort");
        arrow.textContent = "↕";
      }
    });
  }

  // Pipeline filtrar → ordenar → paginar. Las filas nunca salen del <tbody> (solo `hidden`),
  // así que las marcadas en otras páginas u ocultas por el filtro se envían con el formulario.
  function render() {
    var q = norm(state.query.trim());
    var matched = rows.filter(function (tr) {
      return q === "" || norm(tr.dataset.name).indexOf(q) !== -1;
    });
    matched.sort(compare);

    var total = matched.length;
    var totalPages = Math.max(1, Math.ceil(total / state.size));
    state.page = Math.min(state.page, totalPages);
    var start = (state.page - 1) * state.size;
    var visible = matched.slice(start, start + state.size);

    var frag = document.createDocumentFragment();
    matched.forEach(function (tr) {
      frag.appendChild(tr);
    });
    rows.forEach(function (tr) {
      if (matched.indexOf(tr) === -1) {
        frag.appendChild(tr);
      }
    });
    tbody.appendChild(frag);
    rows.forEach(function (tr) {
      tr.hidden = visible.indexOf(tr) === -1;
    });

    emptyMsg.hidden = total !== 0;
    emptyMsg.querySelector("span").textContent = state.query.trim();
    meta.textContent = q === "" ? rows.length + " temas" : total + " de " + rows.length + " temas";

    renderPager(total, totalPages, start, visible.length);
    renderSortHeaders();
    syncSelection();
  }

  function syncSelection() {
    var checked = rows.filter(function (tr) {
      return checkbox(tr).checked;
    });
    var ids = checked.map(function (tr) {
      return checkbox(tr).value;
    });
    var hiddenCount = checked.filter(function (tr) {
      return tr.hidden;
    }).length;

    rows.forEach(function (tr) {
      tr.classList.toggle("is-selected", checkbox(tr).checked);
    });
    countEl.textContent = checked.length;
    bar.classList.toggle("has-selection", checked.length > 0);
    hiddenEl.textContent = hiddenCount > 0 ? "(" + hiddenCount + " fuera de la vista)" : "";
    clearBtn.hidden = checked.length === 0;
    submit.disabled = checked.length < 2;

    // Destino restringido a los marcados; por defecto, el de más resúmenes
    var best = null, bestCount = -1;
    Array.prototype.forEach.call(dest.options, function (opt) {
      var idx = ids.indexOf(opt.value);
      opt.hidden = idx === -1;
      opt.disabled = idx === -1;
      if (idx !== -1) {
        var c = parseInt(checked[idx].dataset.count, 10);
        if (c > bestCount) {
          bestCount = c;
          best = opt;
        }
      }
    });
    var current = dest.options[dest.selectedIndex];
    if (best && (!destPickedByUser || !current || current.disabled)) {
      dest.value = best.value;
      destPickedByUser = false;
    }
    dest.disabled = checked.length === 0;
  }

  search.addEventListener("input", function () {
    state.query = search.value;
    state.page = 1;
    render();
  });

  sizeSelect.addEventListener("change", function () {
    state.size = parseInt(sizeSelect.value, 10);
    state.page = 1;
    render();
  });

  table.querySelectorAll("th[data-sort-key] .sort-btn").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var key = btn.parentNode.dataset.sortKey;
      if (state.key === key) {
        state.dir = state.dir === "asc" ? "desc" : "asc";
      } else {
        state.key = key;
        state.dir = key === "name" ? "asc" : "desc";
      }
      state.page = 1;
      render();
    });
  });

  tbody.addEventListener("change", function (e) {
    if (e.target.type === "checkbox") {
      syncSelection();
    }
  });

  dest.addEventListener("change", function () {
    destPickedByUser = true;
  });

  clearBtn.addEventListener("click", function () {
    rows.forEach(function (tr) {
      checkbox(tr).checked = false;
    });
    destPickedByUser = false;
    syncSelection();
  });

  document.querySelectorAll("[data-topics-js]").forEach(function (el) {
    el.hidden = false;
  });
  render();
})();

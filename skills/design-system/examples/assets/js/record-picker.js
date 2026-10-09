/* record-picker.js — the one modal that chooses a record (design-system reference record-picker.md; app/picker.php).
   A .record-picker-field holds a hidden input (the value) and a button showing the chosen label. Opening sets the
   modal's title and loads the source's first page; typing searches (htmx on the search box, debounced, in-flight
   requests replaced); picking writes value + label into the field, dispatches change on the hidden input (dependent
   refreshes fire, the shell's unsaved-changes guard flags the form), closes and returns focus. Loads after
   vendors.min.js (bootstrap) and htmx.min.js. No state outside the DOM but the field that is open. */
(function () {
    'use strict';
    var modalEl = document.getElementById('record-picker');
    if (!modalEl) { return; }
    var searchInput = document.getElementById('record-picker-search');
    var results = document.getElementById('record-picker-results');
    var titleEl = document.getElementById('record-picker-title');
    var footer = document.getElementById('record-picker-footer');
    var createBtn = document.getElementById('record-picker-create-btn');
    var field = null;
    var shown = false;      // Bootstrap ignores hide() during the opening transition; a pick that early waits for shown.
    function modal() { return bootstrap.Modal.getOrCreateInstance(modalEl, { focus: false }); }
    function hide() { if (shown) { modal().hide(); } else { modalEl.addEventListener('shown.bs.modal', function () { modal().hide(); }, { once: true }); } }

    // What the search box sends: the source, the query, the current value, the field's fixed params and the live
    // values of the elements its include selectors name (their names must be params the source declares).
    window.recordPickerValues = function () {
        if (!field) { return {}; }
        var v = { source: field.dataset.pickerSource, q: searchInput.value.trim() };
        var hidden = field.querySelector('input[data-picker-value]');
        if (hidden && hidden.value) { v.selected = hidden.value; }
        var fixed = {};
        try { fixed = JSON.parse(field.dataset.pickerParams || '{}'); } catch (e) { fixed = {}; }
        Object.keys(fixed).forEach(function (k) { v[k] = fixed[k]; });
        (field.dataset.pickerInclude || '').split(',').forEach(function (sel) {
            sel = sel.trim();
            if (!sel) { return; }
            document.querySelectorAll(sel).forEach(function (el) { if (el.name && el.value !== '') { v[el.name] = el.value; } });
        });
        return v;
    };

    function open(f) {
        field = f;
        titleEl.textContent = f.dataset.pickerTitle || 'Choose';
        searchInput.value = '';
        searchInput.placeholder = f.dataset.pickerSearch || 'Search…';
        if (f.dataset.pickerCreateLabel) { createBtn.textContent = f.dataset.pickerCreateLabel; footer.classList.remove('d-none'); } else { footer.classList.add('d-none'); }
        results.innerHTML = '<div class="p-4 text-center text-muted" id="record-picker-loading">Loading…</div>';
        modal().show();
        htmx.trigger(searchInput, 'pickerload');
    }

    function setValue(f, value, label) {
        var hidden = f.querySelector('input[data-picker-value]'), text = f.querySelector('.record-picker-label'), clear = f.querySelector('.record-picker-clear');
        var changed = hidden.value !== String(value);
        hidden.value = value;
        if (value === '') { text.textContent = f.dataset.pickerPlaceholder || 'Choose…'; text.classList.add('text-muted'); }
        else { text.textContent = label; text.classList.remove('text-muted'); }
        if (clear) { clear.classList.toggle('d-none', value === ''); }
        if (changed) { hidden.dispatchEvent(new Event('change', { bubbles: true })); }
    }

    function pick(row) {
        var f = field;
        hide();
        setValue(f, row.dataset.value, row.dataset.label);
    }

    function create() {
        var f = field, panel = f.dataset.pickerCreatePanel, url = f.dataset.pickerCreateUrl;
        hide();
        if (panel) {
            var p = document.querySelector(panel);
            if (p) { p.classList.remove('d-none'); var first = p.querySelector('input, select, textarea'); if (first) { first.focus(); } }
        } else if (url) {
            window.location.href = url;
        }
    }

    document.addEventListener('click', function (e) {
        var t = e.target;
        var opener = t.closest('.record-picker-open, .record-picker-search-btn');
        if (opener) { if (!opener.disabled) { e.preventDefault(); open(opener.closest('.record-picker-field')); } return; }
        var clear = t.closest('.record-picker-clear');
        if (clear) { e.preventDefault(); setValue(clear.closest('.record-picker-field'), '', ''); return; }
        if (!modalEl.contains(t)) { return; }
        var row = t.closest('.record-picker-row');
        if (row) { e.preventDefault(); pick(row); return; }
        if (t.closest('#record-picker-create-btn')) { e.preventDefault(); create(); }
    });

    // Keyboard: arrows move from the search box through the rows and back; Enter in the search box picks the first row.
    modalEl.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            var rows = Array.prototype.slice.call(results.querySelectorAll('.record-picker-row, .record-picker-more'));
            if (!rows.length) { return; }
            var i = rows.indexOf(document.activeElement);
            var next = e.key === 'ArrowDown' ? (i < 0 ? 0 : Math.min(i + 1, rows.length - 1)) : (i <= 0 ? -1 : i - 1);
            e.preventDefault();
            if (next < 0) { searchInput.focus(); } else { rows[next].focus(); }
        } else if (e.key === 'Enter' && document.activeElement === searchInput) {
            var first = results.querySelector('.record-picker-row');
            if (first) { e.preventDefault(); pick(first); }
        }
    });

    // "Show more" replaces itself with the next rows: keep the keyboard inside the modal by focusing the first new row
    // (a focused element that leaves the DOM drops focus on the body, where Escape no longer closes the dialog).
    var rowsBefore = -1;
    function focusRow(i) { var rows = results.querySelectorAll('.record-picker-row'); var r = rows[i] || rows[rows.length - 1]; if (r) { r.focus(); } else { searchInput.focus(); } }
    results.addEventListener('click', function (e) {
        if (!e.target.closest('.record-picker-more')) { return; }
        rowsBefore = results.querySelectorAll('.record-picker-row').length;
        focusRow(rowsBefore - 1);                        // the last row keeps focus while the button goes away
    });
    results.addEventListener('htmx:afterSettle', function () {
        if (rowsBefore < 0) { return; }
        var n = rowsBefore; rowsBefore = -1;
        if (results.querySelectorAll('.record-picker-row').length > n && document.activeElement !== searchInput) { focusRow(n); }
    });

    modalEl.addEventListener('shown.bs.modal', function () { shown = true; searchInput.focus(); });
    modalEl.addEventListener('hidden.bs.modal', function () {
        shown = false;
        results.innerHTML = '';
        if (field) { var b = field.querySelector('.record-picker-open'); if (b && document.body.contains(b)) { b.focus(); } }
        field = null;
    });
})();

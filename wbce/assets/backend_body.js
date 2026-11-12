/*
 * articles – backend behaviour, vanilla JS. Everything works without it as well:
 * the arrows sort, all fields stay visible, the server checks every input.
 */
(function () {
    'use strict';

    document.querySelectorAll('.art-backend').forEach(function (root) {
        // Confirmations on buttons and links
        root.addEventListener('click', function (e) {
            var el = e.target.closest('[data-art-confirm]');
            if (el && !window.confirm(el.getAttribute('data-art-confirm'))) {
                e.preventDefault();
            }
        });

        // Bulk actions: select all, fields of the chosen action, confirm deleting
        var bulk = root.querySelector('.art-bulk');
        if (bulk) {
            var form = bulk.closest('form');
            var action = bulk.querySelector('.art-bulk-action');
            var all = root.querySelector('.art-check-all');
            var boxes = function () { return form.querySelectorAll('input[name="ids[]"]'); };
            all && all.addEventListener('change', function () {
                boxes().forEach(function (box) { box.checked = all.checked; });
            });
            var show = function () {
                bulk.querySelectorAll('[data-art-for]').forEach(function (el) {
                    el.hidden = el.getAttribute('data-art-for').split(' ').indexOf(action.value) === -1;
                });
            };
            action.addEventListener('change', show);
            show();
            form.addEventListener('submit', function (e) {
                var submitter = e.submitter || null;
                if (submitter && submitter.form !== form) {
                    return;
                }
                var button = bulk.querySelector('[data-art-confirm-delete]');
                if (action.value === 'delete' && !window.confirm(button.getAttribute('data-art-confirm-delete'))) {
                    e.preventDefault();
                }
            });
        }

        // Slug: follows the title of a new article until it is edited, the button unlocks it
        var title = root.querySelector('[data-art-title]');
        var slug = root.querySelector('[data-art-slug]');
        if (title && slug) {
            var locked = root.querySelector('[data-art-slug-locked]');
            var toggle = root.querySelector('[data-art-slug-toggle]');
            var follow = slug.value === '';
            var slugify = function (text) {
                var map = { 'ä': 'ae', 'ö': 'oe', 'ü': 'ue', 'ß': 'ss' };
                return text.toLowerCase().replace(/[äöüß]/g, function (c) { return map[c]; })
                    .normalize('NFD').replace(/[̀-ͯ]/g, '')
                    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            };
            title.addEventListener('input', function () {
                if (follow) {
                    slug.value = slugify(title.value);
                }
            });
            slug.addEventListener('input', function () { follow = false; });
            toggle.addEventListener('click', function () {
                var open = slug.readOnly;
                slug.readOnly = !open;
                locked.value = open ? '0' : '1';
                toggle.setAttribute('aria-pressed', open ? 'true' : 'false');
                if (open) {
                    slug.focus();
                }
            });
        }

        // Drag and drop in the custom order; the hidden form posts the new order
        var body = root.querySelector('.art-sortable');
        if (body) {
            var dragged = null;
            body.querySelectorAll('tr[data-art-id]').forEach(function (row) {
                var grip = row.querySelector('.art-grip');
                grip.addEventListener('mousedown', function () { row.draggable = true; });
                row.addEventListener('dragstart', function (e) {
                    dragged = row;
                    row.classList.add('art-dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', row.getAttribute('data-art-id'));
                });
                row.addEventListener('dragend', function () {
                    row.draggable = false;
                    row.classList.remove('art-dragging');
                    body.querySelectorAll('.art-drop-before').forEach(function (r) { r.classList.remove('art-drop-before'); });
                });
                row.addEventListener('dragover', function (e) {
                    if (dragged && dragged !== row) {
                        e.preventDefault();
                        body.querySelectorAll('.art-drop-before').forEach(function (r) { r.classList.remove('art-drop-before'); });
                        row.classList.add('art-drop-before');
                    }
                });
                row.addEventListener('drop', function (e) {
                    e.preventDefault();
                    if (!dragged || dragged === row) {
                        return;
                    }
                    body.insertBefore(dragged, row);
                    var order = document.getElementById(body.getAttribute('data-art-reorder'));
                    body.querySelectorAll('tr[data-art-id]').forEach(function (r) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'order[]';
                        input.value = r.getAttribute('data-art-id');
                        order.appendChild(input);
                    });
                    order.submit();
                });
            });
        }

        // Another view or gallery replaces templates: only after confirming
        root.querySelectorAll('select[data-art-preset]').forEach(function (select) {
            select.addEventListener('change', function () {
                if (select.value !== select.getAttribute('data-art-initial') && !window.confirm(select.getAttribute('data-art-preset'))) {
                    select.value = select.getAttribute('data-art-initial');
                }
            });
        });

        // Tag form: preview with automatic text color, as in the frontend
        var preview = root.querySelector('.art-tag-preview');
        if (preview) {
            var name = root.querySelector('[data-art-tag-title]');
            var bg = root.querySelector('[data-art-tag-color]');
            var fg = root.querySelector('[data-art-tag-text]');
            var contrast = function (hex) {
                var h = hex.replace('#', '');
                if (h.length === 3) { h = h.replace(/(.)/g, '$1$1'); }
                if (!/^[0-9a-f]{6}$/i.test(h)) { return ''; }
                var yiq = (parseInt(h.substr(0, 2), 16) * 299 + parseInt(h.substr(2, 2), 16) * 587 + parseInt(h.substr(4, 2), 16) * 114) / 1000;
                return yiq >= 140 ? '#000000' : '#ffffff';
            };
            var update = function () {
                preview.textContent = name.value || preview.textContent;
                preview.style.backgroundColor = bg.value.trim();
                preview.style.color = fg.value.trim() || contrast(bg.value.trim());
            };
            [name, bg, fg].forEach(function (el) { el.addEventListener('input', update); });
            update();
        }
    });
})();

import { htmlToElement, postJson, postForm, setupInlineTaskEdit } from './board';
import { alertDialog, choiceDialog, confirmDialog } from './dialog';
import { toast } from './selects';

const RING_BASE_CLASSES = ['ring-1', 'ring-inset'];
const SELECTED_CLASSES = ['bg-indigo-500/25', 'text-indigo-100', 'ring-indigo-400/40'];
const TODAY_CLASSES = ['font-semibold', 'text-sky-300', 'ring-sky-400/60'];
const DEFAULT_CLASSES = ['text-slate-300'];
const ALL_DAY_VARIANT_CLASSES = [
    ...new Set([...RING_BASE_CLASSES, ...SELECTED_CLASSES, ...TODAY_CLASSES, ...DEFAULT_CLASSES]),
];

function dotClassFor(summary) {
    if (!summary || summary.total === 0) {
        return 'bg-transparent';
    }

    const ratio = summary.completed / summary.total;

    if (ratio >= 1) {
        return 'bg-emerald-400';
    }

    return ratio > 0 ? 'bg-amber-400' : 'bg-slate-500';
}

/**
 * Calendar + daily task list for Tarefas. Selecting a day, checking off a
 * task, adding one, or deleting one all go through fetch, swapping in the
 * server-rendered day panel + stats tiles and updating that day's dot on
 * the calendar — without a full page reload.
 */
export function setupTarefas() {
    const page = document.getElementById('tarefas-page');
    const calendar = page;

    if (!page || !calendar) {
        return;
    }

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-task-category-add]');
        if (!button) return;
        const picker = button.closest('[data-task-category-picker]');
        const input = picker.querySelector('[data-task-category-name]');
        const error = picker.querySelector('[data-task-category-error]');
        const name = input.value.trim();
        error.classList.add('hidden');
        if (!name) { input.focus(); error.textContent = 'Informe o nome da categoria.'; error.classList.remove('hidden'); return; }
        button.disabled = true;
        try {
            const category = await postJson(button.dataset.taskCategoryAdd, 'POST', { name });
            document.querySelectorAll('[data-task-category-picker] select').forEach((select) => {
                if (![...select.options].some((option) => option.value === String(category.id))) select.add(new Option(category.name, category.id));
            });
            const select = picker.querySelector('select');
            select.value = String(category.id);
            select.dispatchEvent(new Event('change', { bubbles: true }));
            input.value = '';
        } catch (exception) { error.textContent = exception.message; error.classList.remove('hidden'); }
        finally { button.disabled = false; }
    });

    const hiddenKinds = new Set();
    const filterWeek = () => {
        page.querySelectorAll('[data-week-kind-filter]').forEach((button) => button.setAttribute('aria-pressed', String(!hiddenKinds.has(button.dataset.weekKindFilter))));
        page.querySelectorAll('[data-week-kind]').forEach((item) => { item.hidden = hiddenKinds.has(item.dataset.weekKind); });
    };
    const syncWeekNow = () => {
        const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
            timeZone: 'America/Sao_Paulo', year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
        }).formatToParts(new Date()).map(({ type, value }) => [type, value]));
        const date = `${parts.year}-${parts.month}-${parts.day}`;
        const minute = Number(parts.hour) * 60 + Number(parts.minute);
        page.querySelectorAll('[data-week-date]').forEach((column) => {
            const line = column.querySelector('[data-week-now]');
            if (line) { line.hidden = column.dataset.weekDate !== date; line.style.top = `${minute}px`; }
        });
        return { date, minute };
    };
    const prepareWeek = (scroll = null) => {
        filterWeek();
        const now = syncWeekNow();
        const scroller = page.querySelector('[data-week-scroll]');
        if (!scroller) return;
        if (scroll) { scroller.scrollTop = scroll.top; scroller.scrollLeft = scroll.left; }
        else {
            const currentWeek = Boolean(page.querySelector(`[data-week-date="${now.date}"]`));
            scroller.scrollTop = currentWeek ? Math.max(0, now.minute - 120) : 420;
        }
    };
    page.addEventListener('click', (event) => {
        const button = event.target.closest('[data-week-kind-filter]');
        if (!button) return;
        const kind = button.dataset.weekKindFilter;
        if (hiddenKinds.has(kind)) hiddenKinds.delete(kind); else hiddenKinds.add(kind);
        filterWeek();
    });
    setInterval(syncWeekNow, 60000);

    const applySelection = (dateStr) => {
        const todayStr = page.dataset.today;

        calendar.querySelectorAll('[data-calendar-day]').forEach((button) => {
            const isSelected = button.dataset.calendarDay === dateStr;
            const isToday = button.dataset.calendarDay === todayStr;

            button.classList.remove(...ALL_DAY_VARIANT_CLASSES);

            if (isSelected) {
                button.classList.add(...SELECTED_CLASSES, ...RING_BASE_CLASSES);
            } else if (isToday) {
                button.classList.add(...TODAY_CLASSES, ...RING_BASE_CLASSES);
            } else {
                button.classList.add(...DEFAULT_CLASSES);
            }
        });

        document.getElementById('jump-to-today')?.classList.toggle('hidden', dateStr === todayStr);
        page.querySelectorAll('a[data-view-tab]').forEach((tab) => {
            const url = new URL(tab.href);
            url.searchParams.set('date', dateStr);
            tab.href = url.href;
        });
    };

    const updateDot = (dateStr, summary) => {
        const dot = calendar.querySelector(`[data-calendar-day="${dateStr}"] [data-day-dot]`);

        if (dot) {
            dot.className = `h-1 w-1 rounded-full ${dotClassFor(summary)}`;
        }
    };

    const selectView = (target) => {
        page.querySelectorAll('[data-view-tab]').forEach((tab) => {
            const isActive = tab.dataset.viewTab === target;

            tab.setAttribute('aria-selected', String(isActive));
            tab.classList.toggle('bg-indigo-500/25', isActive);
            tab.classList.toggle('text-indigo-100', isActive);
            tab.classList.toggle('text-slate-300', !isActive);
        });

        page.querySelectorAll('[data-view-panel]').forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.viewPanel !== target);
        });
    };

    const selectChartTab = (target) => {
        const charts = document.getElementById('tarefas-charts');

        if (!charts) {
            return;
        }

        charts.querySelectorAll('[data-chart-tab]').forEach((tab) => {
            const isActive = tab.dataset.chartTab === target;

            tab.setAttribute('aria-selected', String(isActive));
            tab.classList.toggle('bg-indigo-500/25', isActive);
            tab.classList.toggle('text-indigo-100', isActive);
            tab.classList.toggle('text-slate-400', !isActive);
        });

        charts.querySelectorAll('[data-chart-panel]').forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.chartPanel !== target);
        });
    };

    const applyDayUpdate = (data) => {
        document.getElementById('tarefas-day')?.replaceWith(htmlToElement(data.day));
        if (data.calendar) document.getElementById('tarefas-month-calendar')?.replaceWith(htmlToElement(data.calendar));
        const previousWeek = document.getElementById('tarefas-week-calendar');
        if (data.week && previousWeek) {
            const scroller = previousWeek.querySelector('[data-week-scroll]');
            const scroll = scroller ? { top: scroller.scrollTop, left: scroller.scrollLeft } : null;
            previousWeek.replaceWith(htmlToElement(data.week));
            prepareWeek(scroll);
        }
        if (data.overview) document.getElementById('tarefas-overview')?.replaceWith(htmlToElement(data.overview));
        document.getElementById('tarefas-stats')?.replaceWith(htmlToElement(data.stats));

        if (data.charts) {
            const previousCharts = document.getElementById('tarefas-charts');
            const activeTab = previousCharts?.querySelector('[data-chart-tab][aria-selected="true"]')?.dataset.chartTab;

            previousCharts?.replaceWith(htmlToElement(data.charts));

            if (activeTab && activeTab !== 'daily') {
                selectChartTab(activeTab);
            }
        }

        updateDot(data.date, data.summary);
    };

    const infoPopover = document.getElementById('task-info-popover');
    let infoAnchor = null;
    let infoRect = null;
    let infoVersion = 0;
    const closeInfo = (restoreFocus = false) => {
        ++infoVersion;
        if (infoPopover.matches(':popover-open')) infoPopover.hidePopover();
        if (restoreFocus && infoAnchor?.isConnected) infoAnchor.focus();
    };
    const positionInfo = () => {
        if (!infoPopover.matches(':popover-open')) return;
        if (infoAnchor?.isConnected) infoRect = infoAnchor.getBoundingClientRect();
        if (!infoRect) return;
        const box = infoPopover.getBoundingClientRect();
        const left = Math.max(16, Math.min(infoRect.left, window.innerWidth - box.width - 16));
        const below = infoRect.bottom + 10;
        const top = below + box.height <= window.innerHeight - 16 ? below : Math.max(16, Math.min(infoRect.top - box.height - 10, window.innerHeight - box.height - 16));
        infoPopover.style.left = `${left}px`;
        infoPopover.style.top = `${top}px`;
    };
    const openInfo = async (url, anchor) => {
        const version = ++infoVersion;
        try {
            const data = await postJson(url, 'GET');
            if (version !== infoVersion) return;
            infoAnchor = anchor;
            infoRect = anchor.getBoundingClientRect();
            infoPopover.innerHTML = data.summary;
            if (!infoPopover.matches(':popover-open')) infoPopover.showPopover();
            positionInfo();
            infoPopover.querySelector('[data-task-info-close]').focus({ preventScroll: true });
        } catch (error) { alertDialog(error.message); }
    };
    document.addEventListener('click', (event) => {
        if (!infoPopover.contains(event.target) && !event.target.closest('[data-task-details-url]')) closeInfo();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && infoPopover.matches(':popover-open')) {
            closeInfo(true);
            event.stopPropagation();
        }
    });
    window.addEventListener('resize', positionInfo);
    window.addEventListener('scroll', (event) => {
        if (!infoPopover.contains(event.target)) closeInfo();
    }, true);

    const detailModal = document.getElementById('task-details-modal');
    const detailBody = detailModal.querySelector('[data-task-detail-body]');
    let detailVersion = 0;
    const applyDetailUpdate = async (data) => {
        if (data.date !== page.dataset.selectedDate) {
            data = await postJson(`/tarefas?date=${page.dataset.selectedDate}`, 'GET');
        }
        applyDayUpdate(data);
    };
    const detailError = (error) => {
        const message = detailBody.querySelector('[data-task-detail-error]');
        if (message) { message.textContent = error.message; message.classList.remove('hidden'); }
        else alertDialog(error.message);
    };
    const openDetails = async (url) => {
        closeInfo();
        const version = ++detailVersion;
        try {
            const data = await postJson(url, 'GET');
            if (version !== detailVersion) return;
            detailBody.innerHTML = data.html;
            if (!detailModal.open) detailModal.showModal();
        } catch (error) { if (detailModal.open) detailError(error); else alertDialog(error.message); }
    };
    const openCreate = async (date, minute = null) => {
        closeInfo();
        const version = ++detailVersion;
        try {
            const data = await postJson(`/tarefas?date=${encodeURIComponent(date)}`, 'GET');
            if (version !== detailVersion) return;
            const form = htmlToElement(data.day).querySelector('[data-task-create-template]').content.querySelector('[data-add-tarefa-form]');
            form.className = 'mt-4';
            form.querySelector('h3')?.remove();
            form.querySelectorAll('[id]').forEach((field) => {
                const label = form.querySelector(`label[for="${field.id}"]`);
                field.id += '-modal';
                if (label) label.htmlFor = field.id;
            });
            if (minute !== null) {
                const formatTime = (value) => `${String(Math.floor(value / 60)).padStart(2, '0')}:${String(value % 60).padStart(2, '0')}`;
                form.elements.namedItem('start_time').value = formatTime(minute);
                form.elements.namedItem('end_time').value = formatTime(Math.min(1439, minute + 60));
            }
            detailBody.innerHTML = '<div class="flex items-center justify-between gap-4"><h2 id="task-detail-title" class="text-xl font-semibold text-white">Nova tarefa</h2><button type="button" data-task-detail-close aria-label="Fechar cadastro" class="rounded-full border border-white/10 px-3 py-1.5 text-slate-300">✕</button></div><p data-task-detail-error role="alert" class="mt-3 hidden text-sm text-rose-300"></p>';
            detailBody.append(form);
            detailModal.scrollTop = 0;
            if (!detailModal.open) detailModal.showModal();
            form.elements.namedItem('title').focus();
        } catch (error) { if (detailModal.open) detailError(error); else alertDialog(error.message); }
    };

    const deleteTaskWithConfirmation = async (button, isInfo = false) => {
        const restore = () => {
            if (isInfo) { infoPopover.showPopover(); positionInfo(); }
            else detailModal.showModal();
        };
        if (isInfo) closeInfo(); else detailModal.close();
        const scope = button.hasAttribute('data-task-series')
            ? await choiceDialog('Esta tarefa se repete. Os anexos das tarefas excluídas também serão removidos. O que deseja excluir?', [
                { label: 'Só esta tarefa', value: 'one', danger: true },
                { label: 'Esta e as próximas', value: 'series', danger: true },
            ])
            : await confirmDialog('Excluir esta tarefa e seus anexos?', { confirmLabel: 'Excluir tarefa', danger: true }) && 'one';
        if (!scope) { restore(); return; }
        try {
            const url = button.dataset.taskDetailDelete || button.dataset.taskInfoDelete;
            await applyDetailUpdate(await postJson(scope === 'series' ? `${url}?scope=series` : url, 'DELETE'));
            toast('Tarefa excluída.');
        } catch (error) {
            restore();
            if (isInfo) {
                const message = infoPopover.querySelector('[data-task-info-error]');
                message.textContent = error.message;
                message.classList.remove('hidden');
            } else detailError(error);
        }
    };
    infoPopover.addEventListener('click', async (event) => {
        if (event.target.closest('[data-task-info-close]')) { closeInfo(true); return; }
        const edit = event.target.closest('[data-task-info-edit]');
        if (edit) { await openDetails(edit.dataset.taskInfoEdit); return; }
        const remove = event.target.closest('[data-task-info-delete]');
        if (remove) await deleteTaskWithConfirmation(remove, true);
    });
    infoPopover.addEventListener('change', async (event) => {
        const checkbox = event.target.closest('[data-task-info-toggle]');
        if (!checkbox) return;
        checkbox.disabled = true;
        const url = infoPopover.querySelector('[data-task-info-content]').dataset.taskUrl;
        try {
            const data = await postJson(checkbox.dataset.taskInfoToggle, 'PATCH', { completed: checkbox.checked });
            await applyDetailUpdate(data);
            const updated = await postJson(url, 'GET');
            if (infoPopover.matches(':popover-open') && infoPopover.querySelector('[data-task-info-content]')?.dataset.taskUrl === url) {
                infoPopover.innerHTML = updated.summary;
                positionInfo();
                infoPopover.querySelector('[data-task-info-toggle]').focus({ preventScroll: true });
            }
        } catch (error) {
            checkbox.checked = !checkbox.checked;
            checkbox.disabled = false;
            const message = infoPopover.querySelector('[data-task-info-error]');
            message.textContent = error.message;
            message.classList.remove('hidden');
        }
    });

    detailModal.addEventListener('close', () => { ++detailVersion; });
    detailModal.addEventListener('click', async (event) => {
        if (event.target.closest('[data-task-detail-close]') || event.target === detailModal) { detailModal.close(); return; }
        const deleteTask = event.target.closest('[data-task-detail-delete]');
        if (deleteTask) { await deleteTaskWithConfirmation(deleteTask); return; }
        const button = event.target.closest('[data-task-file-delete]');
        if (!button) return;
        detailModal.close();
        const confirmed = await confirmDialog('Remover este anexo?', { confirmLabel: 'Remover', danger: true });
        detailModal.showModal();
        if (!confirmed) return;
        button.disabled = true;
        try {
            const form = detailBody.querySelector('[data-task-detail-form]');
            if (!form.reportValidity()) return;
            await applyDetailUpdate(await postJson(form.action, 'PATCH', Object.fromEntries(new FormData(form))));
            detailBody.innerHTML = (await postJson(button.dataset.taskFileDelete, 'DELETE')).html;
        } catch (error) { detailError(error); }
        finally { button.disabled = false; }
    });
    detailModal.addEventListener('input', () => {
        const form = detailBody.querySelector('[data-task-detail-form]');
        if (form) form.elements.namedItem('end_date').min = form.elements.namedItem('date').value;
    });
    detailModal.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.target;
        if (form.matches('[data-add-tarefa-form]')) {
            if (!form.reportValidity()) return;
            const buttons = form.querySelectorAll('button[type="submit"]');
            const withAttachments = event.submitter?.hasAttribute('data-task-create-details');
            buttons.forEach((button) => { button.disabled = true; });
            try {
                const fields = new FormData(form);
                const payload = Object.fromEntries(fields);
                payload.weekdays = fields.getAll('weekdays[]');
                if (payload.weekdays.length === 0) { delete payload.weekdays; delete payload.repeat_until; }
                delete payload['weekdays[]'];
                const data = await postJson(form.action, 'POST', payload);
                await applyDetailUpdate(data);
                if (withAttachments && data.created_task_url) await openDetails(data.created_task_url);
                else {
                    detailModal.close();
                    const [year, month, day] = fields.get('date').split('-');
                    toast(`Tarefa adicionada em ${day}/${month}/${year}.`);
                }
            } catch (error) { detailError(error); }
            finally { buttons.forEach((button) => { button.disabled = false; }); }
            return;
        }
        const details = detailBody.querySelector('[data-task-detail-form]');
        if (!details.reportValidity() || !form.reportValidity()) return;
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        const url = detailBody.querySelector('[data-task-detail-content]').dataset.taskUrl;
        try {
            const data = await postJson(details.action, 'PATCH', Object.fromEntries(new FormData(details)));
            await applyDetailUpdate(data);
            if (form.matches('[data-task-file-form]')) detailBody.innerHTML = (await postForm(form.action, 'POST', new FormData(form))).html;
            else await openDetails(url);
        } catch (error) { detailError(error); }
        finally { button.disabled = false; }
    });
    page.addEventListener('click', (event) => {
        const editor = event.target.closest('[data-task-editor-url]');
        if (editor) { openDetails(editor.dataset.taskEditorUrl); return; }
        const trigger = event.target.closest('[data-task-details-url]');
        if (trigger) openInfo(trigger.dataset.taskDetailsUrl, trigger);
    });

    const requestedTask = new URLSearchParams(window.location.search).get('task_id');
    if (requestedTask && /^\d+$/.test(requestedTask)) {
        openDetails(`/tarefas/${requestedTask}`);
        const address = new URL(window.location.href);
        address.searchParams.delete('task_id');
        window.history.replaceState({}, '', address);
    }

    let selectionVersion = 0;
    calendar.addEventListener('click', async (event) => {
        if (event.target.closest('[data-task-details-url], [data-calendar-task]')) return;
        const button = event.target.closest('[data-calendar-day], [data-calendar-open], [data-week-slot], [data-calendar-create]');
        if (!button || button.disabled) return;
        const date = button.dataset.calendarDay || button.dataset.calendarOpen || button.dataset.weekSlot || button.dataset.calendarCreate;
        const slotMinute = button.hasAttribute('data-week-slot') ? Math.min(1410, Math.max(0, Math.floor((event.clientY - button.getBoundingClientRect().top) / 30) * 30)) : null;
        if (button.hasAttribute('data-calendar-create') || (['week', 'month'].includes(page.dataset.view) && !button.hasAttribute('data-calendar-more'))) {
            await openCreate(date, slotMinute);
            return;
        }
        if (button.hasAttribute('data-outside-month')) {
            window.location.assign(`?date=${date}&view=month`);
            return;
        }
        const focusDay = () => {
            if (button.hasAttribute('data-calendar-open') || slotMinute !== null) {
                document.getElementById('tarefas-day')?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest' });
                if (button.hasAttribute('data-calendar-add') || slotMinute !== null) {
                    const form = document.querySelector('#tarefas-day [data-add-tarefa-form]');
                    if (form && slotMinute !== null) {
                        const formatTime = (minute) => `${String(Math.floor(minute / 60)).padStart(2, '0')}:${String(minute % 60).padStart(2, '0')}`;
                        form.elements.namedItem('start_time').value = formatTime(slotMinute);
                        form.elements.namedItem('end_time').value = formatTime(Math.min(1439, slotMinute + 60));
                        form.querySelector('[data-new-task-schedule]').open = true;
                    } else if (form && button.hasAttribute('data-week-all-day')) {
                        form.elements.namedItem('start_time').value = '';
                        form.elements.namedItem('end_time').value = '';
                    }
                    form?.querySelector('input[name="title"]')?.focus({ preventScroll: true });
                }
            }
        };
        if (date === page.dataset.selectedDate) { focusDay(); return; }
        const version = ++selectionVersion;
        try {
            const data = await postJson(`/tarefas?date=${date}`, 'GET');
            if (version !== selectionVersion) return;
            applyDayUpdate(data);
            applySelection(date);
            page.dataset.selectedDate = date;
            window.history.pushState({}, '', `?date=${date}&view=${page.dataset.view || 'calendar'}`);
            focusDay();
        } catch (error) {
            alertDialog(error.message);
        }
    });
    window.addEventListener('popstate', () => window.location.reload());

    page.addEventListener('change', async (event) => {
        const checkbox = event.target.closest('[data-task-toggle-url]');

        if (!checkbox) {
            return;
        }

        checkbox.disabled = true;

        try {
            const data = await postJson(checkbox.dataset.taskToggleUrl, 'PATCH', { completed: checkbox.checked });
            await applyDetailUpdate(data);
        } catch (error) {
            checkbox.checked = !checkbox.checked;
            checkbox.disabled = false;
            alertDialog(error.message);
        }
    });

    page.addEventListener('click', (event) => {
        const viewTab = event.target.closest('[data-view-tab]');

        if (viewTab) {
            if (viewTab.tagName === 'A') return;
            selectView(viewTab.dataset.viewTab);

            return;
        }

        const chartTab = event.target.closest('[data-chart-tab]');

        if (chartTab) {
            selectChartTab(chartTab.dataset.chartTab);
        }
    });

    page.addEventListener('click', async (event) => {
        const deleteButton = event.target.closest('[data-delete-task-url]');

        if (!deleteButton) {
            return;
        }

        // A repeated task can go alone or take the rest of its repetition
        // with it; that touches other days' calendar dots, so reload.
        const isSeries = 'taskSeries' in deleteButton.dataset;
        const scope = isSeries
            ? await choiceDialog('Esta tarefa se repete. O que você quer excluir?', [
                  { label: 'Só esta', value: 'one', danger: true },
                  { label: 'Esta e as próximas', value: 'series', danger: true },
              ])
            : (await confirmDialog('Excluir esta tarefa?', { confirmLabel: 'Excluir', danger: true })) && 'one';

        if (!scope) {
            return;
        }

        try {
            const url = deleteButton.dataset.deleteTaskUrl;
            const data = await postJson(scope === 'series' ? `${url}?scope=series` : url, 'DELETE');

            if (scope === 'series') {
                window.location.reload();

                return;
            }

            applyDayUpdate(data);
        } catch (error) {
            alertDialog(error.message);
        }
    });

    document.addEventListener('click', (event) => {
        const preset = event.target.closest('[data-repeat-preset]');

        if (!preset) {
            return;
        }

        const weekdays = preset.dataset.repeatPreset.split(',');

        preset.closest('[data-repeat]').querySelectorAll('input[name="weekdays[]"]').forEach((checkbox) => {
            checkbox.checked = weekdays.includes(checkbox.value);
        });
    });

    setupInlineTaskEdit(page, applyDayUpdate);

    setupTarefaBusca(page);
    selectView(page.dataset.view || 'calendar');
    prepareWeek();

    page.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-task-schedule-form]');
        if (!form) return;
        event.preventDefault();
        if (!form.reportValidity()) return;
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        try {
            applyDayUpdate(await postJson(form.action, 'PATCH', Object.fromEntries(new FormData(form))));
        } catch (error) { alertDialog(error.message); }
        finally { button.disabled = false; }
    });

    page.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-add-tarefa-form]');

        if (!form) {
            return;
        }

        event.preventDefault();

        const input = form.querySelector('input[name="title"]');
        const title = input.value.trim();

        if (!title) {
            return;
        }

        const submitButton = event.submitter || form.querySelector('button[type="submit"]');
        const openCreated = submitButton.hasAttribute('data-task-create-details');
        const submitButtons = form.querySelectorAll('button[type="submit"]');
        submitButtons.forEach((button) => { button.disabled = true; });

        try {
            const formData = new FormData(form);
            const repeats = formData.getAll('weekdays[]').length > 0;
            const payload = { title, date: formData.get('date'), end_date: formData.get('end_date'), notes: formData.get('notes'), category_id: formData.get('category_id'), start_time: formData.get('start_time'), end_time: formData.get('end_time') };

            if (repeats) {
                payload.weekdays = formData.getAll('weekdays[]');
                payload.repeat_until = formData.get('repeat_until');

                if (payload.weekdays.length === 0) {
                    throw new Error('Escolha pelo menos um dia da semana para repetir.');
                }
            }

            const data = await postJson(form.action, 'POST', payload);

            // A repetition adds tasks to other days too, whose calendar
            // dots only a full render refreshes.
            if (repeats) {
                if (openCreated && data.created_task_url) {
                    const taskId = data.created_task_url.split('/').pop();
                    window.location.assign(`?date=${data.date}&view=${page.dataset.view || 'calendar'}&task_id=${encodeURIComponent(taskId)}`);
                    return;
                }
                window.location.reload();

                return;
            }

            applyDayUpdate(data);
            applySelection(data.date);
            page.dataset.selectedDate = data.date;
            if (openCreated && data.created_task_url) await openDetails(data.created_task_url);
        } catch (error) {
            alertDialog(error.message);
        } finally {
            submitButtons.forEach((button) => { button.disabled = false; });
        }
    });
}

/**
 * Search bar above the calendar: looks tasks up across every day by title
 * and/or date range, and lists them with a link to their day.
 */
function setupTarefaBusca(page) {
    const busca = page.querySelector('[data-tarefa-busca]');

    if (!busca) {
        return;
    }

    const titulo = busca.querySelector('[data-tarefa-busca-titulo]');
    const de = busca.querySelector('[data-tarefa-busca-de]');
    const ate = busca.querySelector('[data-tarefa-busca-ate]');
    const limpar = busca.querySelector('[data-tarefa-busca-limpar]');
    const resultados = busca.querySelector('[data-tarefa-busca-resultados]');
    let timer = null;
    let latest = 0;

    const search = async () => {
        const params = new URLSearchParams({ q: titulo.value.trim(), de: de.value, ate: ate.value });
        const ativo = Boolean(params.get('q') || params.get('de') || params.get('ate'));
        const request = ++latest;

        limpar.classList.toggle('hidden', !ativo);

        if (!ativo) {
            resultados.innerHTML = '';

            return;
        }

        try {
            const data = await postJson(`/tarefas/buscar?${params}`, 'GET');

            // A slower, older response must not overwrite a newer one.
            if (request === latest) {
                resultados.innerHTML = data.html;
            }
        } catch (error) {
            alertDialog(error.message);
        }
    };

    [titulo, de, ate].forEach((field) => {
        field.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(search, 250);
        });
    });

    limpar.addEventListener('click', () => {
        titulo.value = '';
        de.value = '';
        ate.value = '';
        search();
    });
}

import { htmlToElement, postJson, setupInlineTaskEdit } from './board';
import { alertDialog, confirmDialog } from './dialog';

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
    const calendar = document.getElementById('tarefas-calendar');

    if (!page || !calendar) {
        return;
    }

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

    calendar.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-calendar-day]');

        if (!button || button.disabled) {
            return;
        }

        const date = button.dataset.calendarDay;

        if (date === page.dataset.selectedDate) {
            return;
        }

        try {
            const data = await postJson(`/tarefas?date=${date}`, 'GET');
            applyDayUpdate(data);
            applySelection(date);
            page.dataset.selectedDate = date;
            window.history.pushState({}, '', `?date=${date}`);
        } catch (error) {
            alertDialog(error.message);
        }
    });

    page.addEventListener('change', async (event) => {
        const checkbox = event.target.closest('[data-task-toggle-url]');

        if (!checkbox) {
            return;
        }

        checkbox.disabled = true;

        try {
            const data = await postJson(checkbox.dataset.taskToggleUrl, 'PATCH', { completed: checkbox.checked });
            applyDayUpdate(data);
        } catch (error) {
            checkbox.checked = !checkbox.checked;
            checkbox.disabled = false;
            alertDialog(error.message);
        }
    });

    page.addEventListener('click', (event) => {
        const viewTab = event.target.closest('[data-view-tab]');

        if (viewTab) {
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

        const confirmed = await confirmDialog('Excluir esta tarefa?', { confirmLabel: 'Excluir', danger: true });

        if (!confirmed) {
            return;
        }

        try {
            const data = await postJson(deleteButton.dataset.deleteTaskUrl, 'DELETE');
            applyDayUpdate(data);
        } catch (error) {
            alertDialog(error.message);
        }
    });

    setupInlineTaskEdit(page, applyDayUpdate);

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

        const submitButton = form.querySelector('button[type="submit"]');
        submitButton.disabled = true;

        try {
            const data = await postJson(form.action, 'POST', Object.fromEntries(new FormData(form)));
            applyDayUpdate(data);
        } catch (error) {
            alertDialog(error.message);
        } finally {
            submitButton.disabled = false;
        }
    });
}

import 'temporal-polyfill/global';
import {
  CalendarApp,
  CalendarRenderer,
  createEvent,
  createDayView,
  createWeekView,
  createMonthView,
  createAgendaView,
} from '@dayflow/core';
import { createDragPlugin } from '@dayflow/plugin-drag';
import '@dayflow/core/dist/styles.css';

function pad(n) {
  return String(n).padStart(2, '0');
}

function toLocalParts(ts) {
  const d = new Date(Number(ts) * 1000);
  if (Number.isNaN(d.getTime())) return null;
  return {
    y: d.getFullYear(),
    m: d.getMonth() + 1,
    day: d.getDate(),
    h: d.getHours(),
    mi: d.getMinutes(),
  };
}

function temporalToUnix(value) {
  if (!value) return 0;
  if (typeof value.toInstant === 'function') {
    return Math.floor(value.toInstant().epochMilliseconds / 1000);
  }
  if (typeof value.toPlainDateTime === 'function') {
    const pdt = value.toPlainDateTime();
    return Math.floor(Date.UTC(pdt.year, pdt.month - 1, pdt.day, pdt.hour || 0, pdt.minute || 0, pdt.second || 0) / 1000);
  }
  if (value.year != null) {
    const h = value.hour || 0;
    const mi = value.minute || 0;
    return Math.floor(new Date(value.year, value.month - 1, value.day, h, mi, 0).getTime() / 1000);
  }
  const d = value instanceof Date ? value : new Date(value);
  return Math.floor(d.getTime() / 1000);
}

function formatDateTime(ts) {
  const p = toLocalParts(ts);
  if (!p) return '';
  return `${p.y}-${pad(p.m)}-${pad(p.day)} ${pad(p.h)}:${pad(p.mi)}`;
}

function taskToEvent(task) {
  const due = Number(task.date_due || 0);
  const started = Number(task.date_started || 0);
  const created = Number(task.date_creation || 0);
  let startTs = started || due || created || Math.floor(Date.now() / 1000);
  let endTs = due || (started ? started + 3600 : startTs + 3600);
  if (endTs <= startTs) endTs = startTs + 3600;
  const allDay = !started && !!due;
  if (allDay) {
    const p = toLocalParts(due);
    return createEvent({
      id: String(task.id),
      title: task.title || `#${task.id}`,
      start: new Date(p.y, p.m - 1, p.day),
      end: new Date(p.y, p.m - 1, p.day),
      allDay: true,
      calendarId: 'tasks',
      meta: { taskId: Number(task.id), url: task.url || '' },
    });
  }
  return createEvent({
    id: String(task.id),
    title: task.title || `#${task.id}`,
    start: new Date(startTs * 1000),
    end: new Date(endTs * 1000),
    allDay: false,
    calendarId: 'tasks',
    meta: { taskId: Number(task.id), url: task.url || '' },
  });
}

async function persistEvent(cfg, event) {
  const taskId = Number((event.meta && event.meta.taskId) || event.id);
  if (!taskId || !cfg.updateUrl) return;
  const startTs = temporalToUnix(event.start);
  const endTs = temporalToUnix(event.end);
  const body = {
    task_id: taskId,
    date_started: event.allDay ? 0 : startTs,
    date_due: event.allDay ? startTs : endTs,
    all_day: !!event.allDay,
    csrf_token: cfg.csrf || '',
  };
  const res = await fetch(cfg.updateUrl, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify(body),
  });
  if (!res.ok) throw new Error('Failed to update task dates');
}

function mountCalendar(root, cfg) {
  const tasks = Array.isArray(cfg.tasks) ? cfg.tasks : [];
  const events = tasks.map(taskToEvent);
  const dark = document.documentElement.classList.contains('kb-dark')
    || (document.body && document.body.classList.contains('kb-dark'));
  const app = new CalendarApp({
    views: [
      createDayView({ showHalfHourLines: true, scrollToCurrentTime: true, showEventDots: true }),
      createWeekView({ showHalfHourLines: true, scrollToCurrentTime: true, showEventDots: true }),
      createMonthView({ showWeekNumbers: true, showEventDots: true }),
      createAgendaView({}),
    ],
    defaultView: 'week',
    events,
    theme: { mode: dark ? 'dark' : 'light' },
    useEventDetailPanel: true,
    eventDetailTrigger: 'click',
    calendars: [{
      id: 'tasks',
      name: 'Tasks',
      isDefault: true,
      isVisible: true,
      colors: {
        eventColor: '#3b82f6',
        eventSelectedColor: '#1d4ed8',
        lineColor: '#2563eb',
        textColor: '#ffffff',
      },
    }],
    plugins: [
      createDragPlugin({
        onEventDrop: async (updated) => {
          try { await persistEvent(cfg, updated); } catch (e) { console.error(e); }
        },
        onEventResize: async (updated) => {
          try { await persistEvent(cfg, updated); } catch (e) { console.error(e); }
        },
      }),
    ],
    callbacks: {
      onEventUpdate: async (event) => {
        try { await persistEvent(cfg, event); } catch (e) { console.error(e); }
      },
      onEventClick: (event) => {
        const url = event.meta && event.meta.url;
        if (url && (event.meta.open === true)) window.location.href = url;
      },
      onEventDoubleClick: (event) => {
        const url = event.meta && event.meta.url;
        if (url) {
          window.location.href = url;
          return false;
        }
        return true;
      },
    },
  });
  const renderer = new CalendarRenderer(app);
  renderer.mount(root);
  return { app, renderer };
}

function boot() {
  const root = document.getElementById('kb-dayflow-calendar');
  if (!root || root.getAttribute('data-mounted') === '1') return;
  let cfg = {};
  try {
    const cfgEl = document.getElementById('kb-dayflow-config');
    if (cfgEl) cfg = JSON.parse(cfgEl.textContent || '{}');
    else cfg = JSON.parse(root.getAttribute('data-config') || '{}');
  } catch (e) {
    cfg = {};
  }
  root.setAttribute('data-mounted', '1');
  mountCalendar(root, cfg);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}

export { mountCalendar, formatDateTime };

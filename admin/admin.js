/* ============================================================
  Kiosk Admin Dashboard — app.js
   All data is fetched from the PHP backend.
   ============================================================ */

/* ── API endpoint map ────────────────────────────────────── */
const API = {
  feedback: '../api/feedback.php',
  analytics: '../api/analytics.php',
  settings: '../api/settings.php',
  changeUsername: '../api/change_username.php',
};

// Auto-refresh interval ID
let dashboardRefreshInterval = null;

/* ── App state ───────────────────────────────────────────── */
let currentPage = 1;
const PAGE_SIZE = 10;
let filteredTotal = 0;
let serverTotalPages = 1;
let currentPageData = [];   // records visible on the current table page
let currentModalId = null;
let currentModalRecord = null;

// Active filter values (kept in sync with the filter bar)
const filters = { search: '', rating: '', sentiment: '', from: '', to: '' };

// Chart instances
let lineChartInst = null;
let pieChartInst = null;
let hourlyInst = null;


// Sidebar global state
let currentFeedbackTotal = parseInt(localStorage.getItem('lastSeenFeedbackCount')) || 0;

/* ── Bootstrap ───────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', async () => {
  try {
    const authData = await apiFetch('../api/authCheck.php');
    if (authData.username) setAdminUsername(authData.username);
  } catch (err) {
    window.location.href = 'login.html';
    return;
  }

  setTopbarDate();
  navigate('dashboard');

  // Sidebar count polling
  updateSidebarFeedbackCount();
  setInterval(updateSidebarFeedbackCount, 30000);

  // Responsive hamburger
  const ham = document.getElementById('hamburger');
  if (ham) ham.style.display = window.innerWidth <= 768 ? 'block' : 'none';
});

window.addEventListener('resize', () => {
  const ham = document.getElementById('hamburger');
  if (ham) ham.style.display = window.innerWidth <= 768 ? 'block' : 'none';
});

/* ── Topbar helpers ──────────────────────────────────────── */
function setTopbarDate() {
  const opts = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
  document.getElementById('topbar-date').textContent =
    new Date().toLocaleDateString('en-US', opts);
}

/**
 * Update every username/avatar element in the UI.
 * Derives avatar initials from the username string.
 */
function setAdminUsername(username) {
  if (!username) return;
  // Compute initials: up to 2 uppercase chars from the username
  const initials = username
    .replace(/[^a-zA-Z0-9]/g, ' ')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(w => w[0].toUpperCase())
    .join('')
    || username.slice(0, 2).toUpperCase();

  const sidebarEl = document.getElementById('sidebar-username');
  const sidebarAvEl = document.getElementById('sidebar-avatar');
  const topbarAvEl = document.getElementById('topbar-avatar');
  const cuDisplayEl = document.getElementById('cu-current-display');
  const cuAvatarEl = document.getElementById('cu-avatar-preview');

  if (sidebarEl) sidebarEl.textContent = username;
  if (sidebarAvEl) sidebarAvEl.textContent = initials;
  if (topbarAvEl) topbarAvEl.textContent = initials;
  if (cuDisplayEl) cuDisplayEl.textContent = username;
  if (cuAvatarEl) cuAvatarEl.textContent = initials;
}

function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}

/* ── Sidebar helpers ─────────────────────────────────────── */
async function updateSidebarFeedbackCount() {
  const badge = document.getElementById('sidebar-feedback-count');
  if (!badge) return;
  try {
    const data = await apiFetch('../api/get_feedback_count.php');
    currentFeedbackTotal = data.count;

    let lastSeen = parseInt(localStorage.getItem('lastSeenFeedbackCount')) || 0;

    // If count drops (deletions), sync down lastSeen to prevent getting stuck
    if (currentFeedbackTotal < lastSeen) {
      lastSeen = currentFeedbackTotal;
      localStorage.setItem('lastSeenFeedbackCount', lastSeen);
    }

    // Hide if currently on the feedback page and sync lastSeen
    const pageFeedback = document.getElementById('page-feedback');
    if (pageFeedback && pageFeedback.classList.contains('active')) {
      lastSeen = currentFeedbackTotal;
      localStorage.setItem('lastSeenFeedbackCount', lastSeen);
    }

    let unread = currentFeedbackTotal - lastSeen;
    if (unread > 0) {
      badge.textContent = unread;
      badge.style.display = 'inline-block';
    } else {
      badge.style.display = 'none';
    }
  } catch (err) {
    badge.style.display = 'none';
  }
}

/* ── Navigation (SPA) ────────────────────────────────────── */
function navigate(page) {
  document.querySelectorAll('.page-section').forEach(p => p.classList.remove('active'));
  // LOW-3 FIX: Reset all nav-item aria-current before setting the active one
  document.querySelectorAll('.nav-item').forEach(n => {
    n.classList.remove('active');
    if (n.id && n.id.startsWith('nav-')) n.setAttribute('aria-current', 'false');
  });

  document.getElementById('page-' + page).classList.add('active');
  const navEl = document.getElementById('nav-' + page);
  if (navEl) {
    navEl.classList.add('active');
    navEl.setAttribute('aria-current', 'page');
  }

  // Logic to clear unread badge instantly on entering the feedback page
  if (page === 'feedback') {
    localStorage.setItem('lastSeenFeedbackCount', currentFeedbackTotal);
    const feedbackBadge = document.getElementById('sidebar-feedback-count');
    if (feedbackBadge) {
      feedbackBadge.style.display = 'none';
    }
  }

  // Clear auto-refresh if leaving dashboard, start if entering dashboard
  if (dashboardRefreshInterval) {
    clearInterval(dashboardRefreshInterval);
    dashboardRefreshInterval = null;
  }

  if (page === 'dashboard') {
    initDashboard();
    // PERF-1 FIX: 30s refresh interval (was 5s — polling an analytics endpoint
    // every 5 seconds is excessive and puts unnecessary load on the DB).
    dashboardRefreshInterval = setInterval(() => {
      // Refresh only if there's no modal open
      if (!document.getElementById('modal-overlay').style.display || document.getElementById('modal-overlay').style.display === 'none') {
        initDashboard(true); // pass true for silent refresh (no loader)
      }
    }, 30000);
  }
  if (page === 'feedback') initFeedbackTable();
  if (page === 'settings') initSettingsPage();
}

/* ── Generic fetch wrapper ───────────────────────────────── */
async function apiFetch(url, options = {}) {
  const res = await fetch(url, {
    headers: { 'Content-Type': 'application/json', ...options.headers },
    credentials: 'same-origin',
    ...options,
  });
  const data = await res.json();
  if (!res.ok || !data.success) {
    throw new Error(data.error || `HTTP ${res.status}`);
  }
  return data;
}

/* ── Page loader helper ──────────────────────────────────── */
function setLoading(containerId, loading) {
  const el = document.getElementById(containerId);
  if (!el) return;
  el.style.opacity = loading ? '0.45' : '1';
  el.style.pointerEvents = loading ? 'none' : '';
}

/* ============================================================
   DASHBOARD OVERVIEW
   ============================================================ */
async function initDashboard(silent = false) {
  // Read selected range. value="0" means "Today"; parseInt('0') → 0 (falsy),
  // so we parse then normalise: NaN → 30, otherwise keep as-is (including 0).
  const rawVal = document.getElementById('dashRange').value;
  const days   = isNaN(parseInt(rawVal, 10)) ? 30 : parseInt(rawVal, 10);
  const isToday = (days === 0);

  if (!silent) setLoading('page-dashboard', true);

  try {
    const data = await apiFetch(`${API.analytics}?days=${days}`);
    const s = data.summary;
    const p = data.prev_summary || {};

    // ── KPI cards — values ───────────────────────────────────
    document.getElementById('m-total').textContent = s.total.toLocaleString();
    document.getElementById('m-avg').textContent = s.avg_rating.toFixed(1);
    document.getElementById('m-positive').textContent = s.positive_pct + '%';
    document.getElementById('m-negative').textContent = s.negative_pct + '%';


    // Peak day — the weekday of the single calendar date with the highest count.
    //
    // PREVIOUS BUG: the old code summed r.total across *all* occurrences of each
    // weekday in the window (e.g. all 13 Tuesdays in a 90-day range). This caused
    // Tuesday to "win" even when a single Wednesday had a higher daily count,
    // because the cumulative Tuesday total outweighed it.
    //
    // CORRECT: reduce trend to the one date row whose total is highest, then
    // derive its day-of-week. This always matches the actual highest-count date.
    //
    // NOTE: new Date('YYYY-MM-DD') parses as UTC midnight and shifts the weekday
    // by -1 in UTC+8 timezones; we use the 3-arg constructor (local time) instead.
    const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const peakEntry = data.trend.reduce(
      (best, r) => (!best || r.total > best.total) ? r : best,
      null
    );
    if (peakEntry && peakEntry.total > 0) {
      const [y, m, d] = peakEntry.date.split('-').map(Number);
      const dow = new Date(y, m - 1, d).getDay(); // local time — no UTC shift
      document.getElementById('a-peak-day').textContent = dayNames[dow] || '—';
    } else {
      document.getElementById('a-peak-day').textContent = '—';
    }

    // ── Charts ───────────────────────────────────────────────
    buildLineChart(data.trend, isToday);
    buildPieChart(s.positive, s.neutral, s.negative);
    buildRecentTable(data.recent);
    buildRatingBreakdown(data.rating_breakdown);
    buildHourlyChart(data.hourly);
    updateRefreshTimestamp();
  } catch (err) {
    if (!silent) showToast('Could not load dashboard data: ' + err.message, true);
  } finally {
    if (!silent) setLoading('page-dashboard', false);
  }
}

function updateDashboardRange() { initDashboard(); }


/* ── Live refresh timestamp ──────────────────────────── */
function updateRefreshTimestamp() {
  const el = document.getElementById('dash-last-updated');
  if (el) el.textContent = 'Updated ' + new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}


/* ── Feedback Trend Line Chart ───────────────────────────── */
function buildLineChart(trend, isToday = false) {
  if (lineChartInst) lineChartInst.destroy();

  // For "Today" there is normally only one data point; skip thinning entirely.
  const skip = (!isToday && trend.length > 30) ? 3 : (!isToday && trend.length > 14) ? 2 : 1;
  const subset = trend.filter((_, i) => i % skip === 0);
  const labels = subset.map((r, i) => {
    if (isToday) return 'Today';
    const d = new Date(r.date);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  });
  const values = subset.map(r => r.total);

  const ctx = document.getElementById('lineChart').getContext('2d');
  const grad = ctx.createLinearGradient(0, 0, 0, 200);
  grad.addColorStop(0, 'rgba(59,130,246,0.15)');
  grad.addColorStop(1, 'rgba(59,130,246,0)');

  lineChartInst = new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Feedback',
        data: values,
        borderColor: '#3b82f6',
        backgroundColor: grad,
        borderWidth: 2,
        pointRadius: 3,
        pointBackgroundColor: '#3b82f6',
        tension: 0.4,
        fill: true,
      }],
    },
    options: chartBaseOptions({ legend: false }),
  });
}

/* ── Sentiment Donut Chart ───────────────────────────────── */
function buildPieChart(pos, neu, neg) {
  if (pieChartInst) pieChartInst.destroy();

  const ctx = document.getElementById('pieChart').getContext('2d');
  pieChartInst = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: ['Positive', 'Neutral', 'Negative'],
      datasets: [{
        data: [pos, neu, neg],
        backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
        borderWidth: 0,
        hoverOffset: 4,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '68%',
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: ctx => {
              const total = pos + neu + neg || 1;
              return ` ${ctx.label}: ${ctx.raw} (${Math.round(ctx.raw / total * 100)}%)`;
            },
          },
        },
      },
    },
  });
}

/* ── Recent Feedback Mini-Table ──────────────────────────── */
function buildRecentTable(recent) {
  document.getElementById('recentFeedbackBody').innerHTML = recent.map(f => `
    <tr>
      <td>${renderStars(Math.round(f.overall_rating))}</td>
      <td>
        <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px;font-size:13px">
          ${escHtml(f.comment) || '<span style="color:#d1d5db;font-style:italic">No comment</span>'}
        </div>
      </td>
      <td><span class="sentiment-badge sentiment-${f.sentiment}">
        ${sentimentDot(f.sentiment)}${capitalize(f.sentiment)}
      </span></td>
      <td style="font-size:12px;color:#9ca3af;white-space:nowrap;font-family:'DM Mono',monospace">
        ${formatDate(new Date(f.submitted_at))}
      </td>
    </tr>
  `).join('');
}

/* ── Rating Breakdown Bars ───────────────────────────────── */
function buildRatingBreakdown(breakdown) {
  const colors = { 5: '#10b981', 4: '#3b82f6', 3: '#f59e0b', 2: '#f97316', 1: '#ef4444' };
  const maxVal = Math.max(...Object.values(breakdown), 1);

  document.getElementById('ratingBreakdown').innerHTML = [5, 4, 3, 2, 1].map(star => `
    <div style="display:flex;align-items:center;gap:10px">
      <div style="font-size:11.5px;color:#6b7280;min-width:14px;text-align:right;font-family:'DM Mono',monospace">
        ${star}
      </div>
      <svg width="11" height="11" viewBox="0 0 24 24" fill="${colors[star]}" stroke="none">
        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
      </svg>
      <div class="stat-bar" style="flex:1">
        <div class="stat-bar-fill" style="width:${Math.round((breakdown[star] || 0) / maxVal * 100)}%;background:${colors[star]}"></div>
      </div>
      <div style="font-size:11.5px;color:#9ca3af;min-width:30px;text-align:right;font-family:'DM Mono',monospace">
        ${breakdown[star] || 0}
      </div>
    </div>
  `).join('');
}

/* ============================================================
   FEEDBACK MANAGEMENT — TABLE (server-side pagination)
   ============================================================ */
function initFeedbackTable() {
  // Reset filters and reload
  Object.keys(filters).forEach(k => filters[k] = '');
  document.getElementById('searchInput').value = '';
  document.getElementById('ratingFilter').value = '';
  document.getElementById('sentimentFilter').value = '';
  document.getElementById('dateFrom').value = '';
  document.getElementById('dateTo').value = '';
  currentPage = 1;
  fetchFeedbackPage();
}

/* ── Read filter bar into state ──────────────────────────── */
function applyFilters() {
  filters.search = document.getElementById('searchInput').value.trim();
  filters.rating = document.getElementById('ratingFilter').value;
  filters.sentiment = document.getElementById('sentimentFilter').value;
  filters.from = document.getElementById('dateFrom').value;
  filters.to = document.getElementById('dateTo').value;
  currentPage = 1;
  fetchFeedbackPage();
}

function clearFilters() {
  Object.keys(filters).forEach(k => filters[k] = '');
  document.getElementById('searchInput').value = '';
  document.getElementById('ratingFilter').value = '';
  document.getElementById('sentimentFilter').value = '';
  document.getElementById('dateFrom').value = '';
  document.getElementById('dateTo').value = '';
  currentPage = 1;
  fetchFeedbackPage();
}

/* ── Fetch current page from server ─────────────────────── */
async function fetchFeedbackPage() {
  const params = new URLSearchParams({
    page: currentPage,
    limit: PAGE_SIZE,
    search: filters.search,
    rating: filters.rating,
    sentiment: filters.sentiment,
    from: filters.from,
    to: filters.to,
  });

  const tbody = document.getElementById('feedbackTableBody');
  tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:40px">
    <div style="display:inline-flex;align-items:center;gap:8px">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2"
           style="animation:spin 1s linear infinite">
        <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
      </svg> Loading…
    </div>
  </td></tr>`;

  try {
    const data = await apiFetch(`${API.feedback}?${params}`);
    currentPageData = data.data;
    filteredTotal = data.total;
    serverTotalPages = data.pages;

    document.getElementById('resultsCount').textContent = filteredTotal;
    document.getElementById('currentPageLabel').textContent = currentPage;
    document.getElementById('totalPagesLabel').textContent = serverTotalPages;

    renderTable(data.data);
    renderPagination();
  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#ef4444;padding:40px">
      Failed to load: ${escHtml(err.message)}
    </td></tr>`;
  }
}

/* ── Render table rows ───────────────────────────────────── */
function renderTable(records) {
  const tbody = document.getElementById('feedbackTableBody');
  const start = (currentPage - 1) * PAGE_SIZE;

  if (!records.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:40px">
      No feedback found matching your filters.
    </td></tr>`;
    return;
  }

  tbody.innerHTML = records.map((f, i) => {
    const starRating = Math.round(f.overall_rating);
    return `
    <tr>
      <td style="font-size:12px;color:#d1d5db;font-family:'DM Mono',monospace">${start + i + 1}</td>
      <td>
        <div style="display:flex;align-items:center;gap:8px">
          <div style="width:28px;height:28px;border-radius:50%;
                      background:linear-gradient(135deg,#3b82f6,#6366f1);
                      display:flex;align-items:center;justify-content:center;
                      font-size:10px;font-weight:700;color:white;flex-shrink:0">
            ${langFlag(f.language)}
          </div>
          <span style="font-size:13px;font-weight:500;color:#6b7280">
            ${capitalize(f.language)} · #${f.id}
          </span>
        </div>
      </td>
      <td>${renderStars(starRating)}</td>
      <td style="max-width:280px">
        <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px;font-size:13px;color:#4b5563">
          ${escHtml(f.comment) || '<span style="color:#d1d5db;font-style:italic">No comment</span>'}
        </div>
      </td>
      <td><span class="sentiment-badge sentiment-${f.sentiment}">
        ${sentimentDot(f.sentiment)}${capitalize(f.sentiment)}
      </span></td>
      <td style="font-size:12px;color:#9ca3af;white-space:nowrap;font-family:'DM Mono',monospace">
        ${formatDateTime(new Date(f.submitted_at))}
      </td>
      <td>
        <div style="display:flex;gap:6px">
          <button onclick="openModal(${f.id})" title="View detail"
            style="width:28px;height:28px;border:1px solid #e5eaf0;border-radius:6px;
                   background:white;cursor:pointer;display:flex;align-items:center;
                   justify-content:center;transition:all 0.15s"
            onmouseover="this.style.borderColor='#3b82f6'"
            onmouseout="this.style.borderColor='#e5eaf0'">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
          <button onclick="deleteFeedback(${f.id})" title="Delete"
            style="width:28px;height:28px;border:1px solid #e5eaf0;border-radius:6px;
                   background:white;cursor:pointer;display:flex;align-items:center;
                   justify-content:center;transition:all 0.15s"
            onmouseover="this.style.borderColor='#ef4444';this.style.background='#fff5f5'"
            onmouseout="this.style.borderColor='#e5eaf0';this.style.background='white'">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2">
              <polyline points="3 6 5 6 21 6"/>
              <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
            </svg>
          </button>
        </div>
      </td>
    </tr>`;
  }).join('');
}

/* ── Pagination ──────────────────────────────────────────── */
function renderPagination() {
  const container = document.getElementById('paginationContainer');
  const total = serverTotalPages;

  let html = `<button class="page-btn" onclick="goPage(${currentPage - 1})"
    ${currentPage === 1 ? 'disabled style="opacity:0.4;cursor:default"' : ''}>&lsaquo;</button>`;

  for (let i = 1; i <= total; i++) {
    if (total <= 7 || i === 1 || i === total || Math.abs(i - currentPage) <= 1) {
      html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goPage(${i})">${i}</button>`;
    } else if (Math.abs(i - currentPage) === 2) {
      html += `<span style="color:#9ca3af;font-size:13px;padding:0 4px">…</span>`;
    }
  }

  html += `<button class="page-btn" onclick="goPage(${currentPage + 1})"
    ${currentPage === total ? 'disabled style="opacity:0.4;cursor:default"' : ''}>&rsaquo;</button>`;
  container.innerHTML = html;
}

function goPage(p) {
  if (p < 1 || p > serverTotalPages) return;
  currentPage = p;
  fetchFeedbackPage();
  window.scrollTo({ top: 120, behavior: 'smooth' });
}

/* ── Delete record ───────────────────────────────────────── */
async function deleteFeedback(id) {
  if (!confirm('Delete this feedback entry?')) return;
  try {
    await apiFetch(API.feedback, {
      method: 'DELETE',
      body: JSON.stringify({ id }),
    });
    showToast('Feedback deleted');
    fetchFeedbackPage();
    updateSidebarFeedbackCount();
  } catch (err) {
    showToast('Delete failed: ' + err.message, true);
  }
}

/* ── Clear all feedback ──────────────────────────────────── */
async function clearAllFeedback() {
  if (!confirm('Are you sure you want to delete all feedback? This action cannot be undone.')) return;

  const btn = document.getElementById('btnClearAll');
  if (btn) {
    btn.disabled = true;
    btn.style.opacity = '0.5';
    btn.style.cursor = 'wait';
  }

  try {
    await apiFetch('../api/clear_feedback.php', { method: 'DELETE' });
    showToast('All feedback cleared');
    fetchFeedbackPage();
    updateSidebarFeedbackCount();
    // Also quietly refresh dashboard metrics if we jump back to it
    initDashboard(true);
  } catch (err) {
    showToast('Failed to clear feedback: ' + err.message, true);
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.style.opacity = '1';
      btn.style.cursor = 'pointer';
    }
  }
}

/* ============================================================
   FEEDBACK DETAIL MODAL
   ============================================================ */
function openModal(id) {
  const f = currentPageData.find(r => r.id === id);
  if (!f) return;
  currentModalId = id;
  currentModalRecord = f;

  const starRating = Math.round(f.overall_rating);

  document.getElementById('modal-avatar').textContent = langFlag(f.language);
  document.getElementById('modal-customer').textContent = `Entry #${f.id} · ${capitalize(f.language)}`;
  document.getElementById('modal-date').textContent = formatDateTime(new Date(f.submitted_at));
  document.getElementById('modal-message').textContent = f.comment || '(no comment provided)';
  document.getElementById('modal-stars').innerHTML =
    renderStars(starRating) +
    `<span style="font-size:12px;color:#9ca3af;margin-left:4px">(${f.overall_rating}/5)</span>`;
  document.getElementById('modal-sentiment-badge').innerHTML =
    `<span class="sentiment-badge sentiment-${f.sentiment}">
       ${sentimentDot(f.sentiment)}${capitalize(f.sentiment)}
     </span>`;

  // Show individual question breakdown in modal
  const qLabels = ['Cleanliness', 'Staff', 'Speed', 'Quality', 'Overall'];
  const qValues = [f.q1_rating, f.q2_rating, f.q3_rating, f.q4_rating, f.q5_rating];

  let questionHTML = `<div style="margin-top:12px;padding-top:12px;border-top:1px solid #f1f4f8">
    <div style="font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:10px">
      Per-Question Breakdown
    </div>
    <div style="display:flex;flex-direction:column;gap:7px">`;

  qLabels.forEach((label, i) => {
    const val = qValues[i] || 0;
    const colors = ['#ef4444', '#f97316', '#f59e0b', '#3b82f6', '#10b981'];
    const color = colors[val - 1] || '#d1d5db';
    questionHTML += `
      <div style="display:flex;align-items:center;gap:10px">
        <div style="font-size:11.5px;color:#6b7280;width:76px">${label}</div>
        <div class="stat-bar" style="flex:1">
          <div class="stat-bar-fill" style="width:${val * 20}%;background:${color}"></div>
        </div>
        <div style="font-size:11.5px;font-family:'DM Mono',monospace;color:#374151;min-width:20px">${val}/5</div>
      </div>`;
  });
  questionHTML += `</div></div>`;

  // Append to modal-message container
  const msgEl = document.getElementById('modal-message');
  // Remove old breakdown if exists
  const oldBreakdown = document.getElementById('modal-breakdown');
  if (oldBreakdown) oldBreakdown.remove();

  const breakdownDiv = document.createElement('div');
  breakdownDiv.id = 'modal-breakdown';
  breakdownDiv.innerHTML = questionHTML;
  msgEl.parentNode.insertBefore(breakdownDiv, msgEl.nextSibling);

  document.getElementById('modal-overlay').style.display = 'block';
  document.getElementById('modal').style.display = 'block';
}

function closeModal() {
  document.getElementById('modal-overlay').style.display = 'none';
  document.getElementById('modal').style.display = 'none';
  const old = document.getElementById('modal-breakdown');
  if (old) old.remove();
  currentModalId = null;
  currentModalRecord = null;
}

async function deleteFeedbackFromModal() {
  if (!currentModalId) return;
  await deleteFeedback(currentModalId);
  closeModal();
}



/* ── Analytics: Hourly activity ──────────────────────────── */
function buildHourlyChart(hourly) {
  const labels = Array.from({ length: 24 }, (_, i) =>
    i === 0 ? '12am' : i < 12 ? `${i}am` : i === 12 ? '12pm' : `${i - 12}pm`
  );
  const ctx = document.getElementById('hourlyChart').getContext('2d');
  if (hourlyInst) hourlyInst.destroy();
  hourlyInst = new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{ label: 'Submissions', data: hourly, backgroundColor: 'rgba(59,130,246,0.7)', borderRadius: 3, borderSkipped: false }],
    },
    options: {
      ...chartBaseOptions({ legend: false }),
      scales: {
        x: { grid: { display: false }, ticks: { font: { size: 9 }, color: '#9ca3af', maxTicksLimit: 12 } },
        y: { grid: { color: '#f1f4f8' }, ticks: { font: { size: 10 }, color: '#9ca3af' }, beginAtZero: true },
      },
    },
  });
}



/* ── Shared Chart.js base options ────────────────────────── */
function chartBaseOptions({ legend = true, legendPos = 'bottom' } = {}) {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: legend
        ? { position: legendPos, labels: { font: { size: 11, family: 'DM Sans' }, usePointStyle: true, pointStyleWidth: 8 } }
        : { display: false },
    },
    scales: {
      x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#9ca3af', maxTicksLimit: 8 } },
      y: { grid: { color: '#f1f4f8' }, ticks: { font: { size: 10 }, color: '#9ca3af' }, beginAtZero: true },
    },
  };
}

/* ============================================================
   GENERAL SETTINGS PAGE
   ============================================================ */
async function initSettingsPage() {
  setLoading('page-settings', true);
  try {
    const data = await apiFetch(API.settings);

    setValue('setting-business-name', data.business_name);
    setValue('setting-default-language', data.default_language);
    setValue('setting-logo-url', data.logo_url);
    setValue('setting-primary-color', data.primary_color);

    setToggle('setting-anonymous-feedback', data.anonymous_feedback);

    // Re-sync username display in the Change Username section
    const sidebarUsername = document.getElementById('sidebar-username');
    if (sidebarUsername && sidebarUsername.textContent.trim() !== '…') {
      const uname = sidebarUsername.textContent.trim();
      const cuDisplay = document.getElementById('cu-current-display');
      const cuAvatar = document.getElementById('cu-avatar-preview');
      if (cuDisplay) cuDisplay.textContent = uname;
      if (cuAvatar) cuAvatar.textContent = document.getElementById('sidebar-avatar')?.textContent || uname.slice(0, 2).toUpperCase();
    }
  } catch (err) {
    showToast('Could not load settings', true);
  } finally {
    setLoading('page-settings', false);
  }
}

async function saveSettings() {
  const payload = {
    business_name: getValueStr('setting-business-name'),
    default_language: getValueStr('setting-default-language'),
    logo_url: getValueStr('setting-logo-url'),
    primary_color: getValueStr('setting-primary-color'),
    anonymous_feedback: getToggle('setting-anonymous-feedback'),
  };

  try {
    await apiFetch(API.settings, { method: 'POST', body: JSON.stringify(payload) });
    showToast('Settings saved successfully');
  } catch (err) {
    showToast('Save failed: ' + err.message, true);
  }
}

/* ── Settings form helpers ───────────────────────────────── */
function setValue(id, val) {
  const el = document.getElementById(id);
  if (el) el.value = val ?? '';
}
function getValueStr(id) {
  const el = document.getElementById(id);
  return el ? el.value.trim() : '';
}
function setToggle(id, val) {
  const el = document.getElementById(id);
  if (el) el.checked = !!val;
}
function getToggle(id) {
  const el = document.getElementById(id);
  return el ? el.checked : false;
}

/* ============================================================
   CHANGE PASSWORD
   ============================================================ */
async function changePassword() {
  const current = document.getElementById('cp-current').value;
  const newPw = document.getElementById('cp-new').value;
  const confirm = document.getElementById('cp-confirm').value;

  // Client-side validation
  if (!current || !newPw || !confirm) {
    showToast('Please fill in all password fields', true);
    return;
  }
  if (newPw.length < 8) {
    showToast('New password must be at least 8 characters', true);
    return;
  }
  if (newPw !== confirm) {
    showToast('New passwords do not match', true);
    document.getElementById('cp-confirm').focus();
    return;
  }

  const btn = document.getElementById('btn-change-pw');
  if (btn) { btn.disabled = true; btn.style.opacity = '0.65'; btn.style.cursor = 'wait'; }

  try {
    const res = await fetch('../api/change_password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ current_password: current, new_password: newPw, confirm_password: confirm }),
    });
    const data = await res.json();

    if (data.success) {
      showToast('Password updated successfully ✓');
      // Clear fields and reset strength bar
      document.getElementById('cp-current').value = '';
      document.getElementById('cp-new').value = '';
      document.getElementById('cp-confirm').value = '';
      checkPasswordStrength(''); // reset bars
    } else {
      // LOW-2 FIX: changed_password.php now emits 'error' key (was 'message')
      showToast(data.error || 'Failed to update password', true);
    }
  } catch (err) {
    showToast('Network error: ' + err.message, true);
  } finally {
    if (btn) { btn.disabled = false; btn.style.opacity = '1'; btn.style.cursor = 'pointer'; }
  }
}

/* ============================================================
   CHANGE USERNAME
   ============================================================ */
/* ── Inline validation hint while typing ─────────────────── */
function validateUsernameInput(input) {
  const val = input.value.trim();
  const hint = document.getElementById('cu-username-hint');
  if (!hint) return;

  if (!val) {
    hint.textContent = '3–50 chars · letters, numbers, _ – .';
    hint.style.color = '#9ca3af';
    return;
  }
  if (val.length < 3) {
    hint.textContent = 'Too short (min 3 characters)';
    hint.style.color = '#ef4444';
    return;
  }
  if (!/^[a-zA-Z0-9_.\-]+$/.test(val)) {
    hint.textContent = 'Only letters, numbers, underscores, hyphens and dots allowed';
    hint.style.color = '#ef4444';
    return;
  }
  hint.textContent = '✓ Looks good';
  hint.style.color = '#10b981';
}

async function changeUsername() {
  const newUsername = document.getElementById('cu-new-username').value.trim();
  const password = document.getElementById('cu-password').value;

  if (!newUsername) {
    showToast('Please enter a new username', true);
    document.getElementById('cu-new-username').focus();
    return;
  }
  if (newUsername.length < 3) {
    showToast('Username must be at least 3 characters', true);
    return;
  }
  if (!/^[a-zA-Z0-9_.\-]+$/.test(newUsername)) {
    showToast('Username contains invalid characters', true);
    return;
  }
  if (!password) {
    showToast('Enter your current password to confirm', true);
    document.getElementById('cu-password').focus();
    return;
  }

  const btn = document.getElementById('btn-change-username');
  if (btn) { btn.disabled = true; btn.style.opacity = '0.65'; btn.style.cursor = 'wait'; }

  try {
    const data = await apiFetch(API.changeUsername, {
      method: 'POST',
      body: JSON.stringify({ new_username: newUsername, current_password: password }),
    });

    // Update all username displays in the UI
    setAdminUsername(data.username || newUsername);
    showToast('Username updated to "' + (data.username || newUsername) + '" ✓');

    // Clear fields
    document.getElementById('cu-new-username').value = '';
    document.getElementById('cu-password').value = '';
    validateUsernameInput(document.getElementById('cu-new-username'));
  } catch (err) {
    showToast(err.message || 'Failed to update username', true);
  } finally {
    if (btn) { btn.disabled = false; btn.style.opacity = '1'; btn.style.cursor = 'pointer'; }
  }
}

/* ── Password strength indicator ─────────────────────────── */
// Scores length + character variety; penalises >60% repeated chars.
function getPasswordStrength(pw) {
  if (!pw) return '';

  let score = 0;

  // ── Length bonus ────────────────────────────────────────
  const len = pw.length;
  if (len >= 16) score += 3;
  else if (len >= 12) score += 2;
  else if (len >= 8) score += 1;
  // < 8 chars → no length bonus

  // ── Character-type bonus ─────────────────────────────────
  if (/[a-z]/.test(pw)) score += 1;   // lowercase
  if (/[A-Z]/.test(pw)) score += 1;   // uppercase
  if (/[0-9]/.test(pw)) score += 1;   // digit
  if (/[^A-Za-z0-9]/.test(pw)) score += 1;   // symbol / special

  // ── Repetition penalty ───────────────────────────────────
  // Penalise if more than 60 % of characters are a single repeated char
  const maxRepeat = Math.max(
    ...Object.values(
      pw.split('').reduce((acc, c) => { acc[c] = (acc[c] || 0) + 1; return acc; }, {})
    )
  );
  if (maxRepeat / len > 0.6) score -= 1;

  // ── Classify ─────────────────────────────────────────────
  if (score >= 5) return 'strong';
  else if (score >= 3) return 'medium';
  else return 'weak';
}

function checkPasswordStrength(pw) {
  const bars = [1, 2, 3].map(n => document.getElementById('cp-bar-' + n)).filter(Boolean);
  const label = document.getElementById('cp-strength-label');
  if (!bars.length) return;

  if (!pw) {
    bars.forEach(b => (b.style.background = '#e5eaf0'));
    if (label) { label.textContent = ''; label.style.color = '#9ca3af'; }
    return;
  }

  const strength = getPasswordStrength(pw);

  // Visual config per level — 1 bar = Weak, 2 = Medium, 3 = Strong
  const config = {
    weak: { filledBars: 1, color: '#ef4444', text: 'Weak' },
    medium: { filledBars: 2, color: '#f59e0b', text: 'Medium' },
    strong: { filledBars: 3, color: '#10b981', text: 'Strong' },
  };

  const { filledBars, color, text } = config[strength];

  bars.forEach((bar, i) => {
    bar.style.background = i < filledBars ? color : '#e5eaf0';
  });

  if (label) {
    label.textContent = text;
    label.style.color = color;
  }
}

/* ── Toggle password field visibility ────────────────────── */
function togglePwVisibility(inputId, eyeId) {
  const input = document.getElementById(inputId);
  const eye = document.getElementById(eyeId);
  if (!input || !eye) return;

  const isText = input.type === 'text';
  input.type = isText ? 'password' : 'text';

  // Swap icon between open-eye and crossed-eye
  eye.innerHTML = isText
    ? `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`
    : `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
       <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
       <line x1="1" y1="1" x2="23" y2="23"/>`;
}

/* ============================================================
   CSV EXPORT — FEEDBACK MANAGEMENT PAGE
   (exports the current filtered feedback table)
   ============================================================ */
function exportCSV() {
  const params = new URLSearchParams({
    export: 'csv',
    search: filters.search,
    rating: filters.rating,
    sentiment: filters.sentiment,
    from: filters.from,
    to: filters.to,
  });
  // Opens the PHP endpoint directly → browser downloads the file
  window.open(`${API.feedback}?${params}`, '_blank');
}

/* ============================================================
   SHARED UTILITIES
   ============================================================ */
function renderStars(rating) {
  return Array.from({ length: 5 }, (_, i) =>
    `<span style="color:${i < rating ? '#f59e0b' : '#e5e7eb'};font-size:13px">★</span>`
  ).join('');
}

function sentimentDot(sentiment) {
  const c = { positive: '#059669', neutral: '#d97706', negative: '#dc2626' };
  return `<span style="width:6px;height:6px;border-radius:50%;background:${c[sentiment] || '#9ca3af'};display:inline-block"></span>`;
}

function langFlag(lang) {
  const flags = { en: '🇺🇸', fil: '🇵🇭', es: '🇪🇸', zh: '🇨🇳', ja: '🇯🇵', ar: '🇸🇦' };
  return flags[lang] || '🌐';
}

function capitalize(s) {
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
}

function formatDate(d) {
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatDateTime(d) {
  return formatDate(d) + ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

/** HTML-escape user content to prevent XSS */
function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function showToast(message, isError = false) {
  const toast = document.getElementById('toast');
  const toastMsg = document.getElementById('toast-msg');
  toastMsg.textContent = message;
  toast.style.background = isError ? '#dc2626' : '#0f172a';
  toast.classList.add('show');
  setTimeout(() => toast.classList.remove('show'), 3200);
}

/* Add CSS keyframe for the loading spinner inline */
const spinStyle = document.createElement('style');
spinStyle.textContent = '@keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }';
document.head.appendChild(spinStyle);

/**
 * kiosk.js — Customer Feedback Kiosk (v3)
 * Multi-language, idle countdown, offline sync, brand color from settings.
 */

(function () {
  "use strict";

  // ── Translations ───────────────────────────────────────────────────────────
  const LANGUAGES = {
    en: { code: "en", flag: "🇺🇸", label: "EN" },
    fil: { code: "fil", flag: "🇵🇭", label: "FIL" },
    es: { code: "es", flag: "🇪🇸", label: "ES" },
    zh: { code: "zh", flag: "🇨🇳", label: "ZH" },
    ja: { code: "ja", flag: "🇯🇵", label: "JA" },
    ar: { code: "ar", flag: "🇸🇦", label: "AR" },
  };

  const TRANSLATIONS = {
    en: {
      veryBad: "Very Bad",
      bad: "Bad",
      neutral: "Neutral",
      good: "Good",
      excellent: "Excellent",
      back: "← Back",
      submitFeedback: "Submit Feedback",
      thankYou: "Thank You!",
      feedbackReceived:
        "Your feedback has been received.\nWe appreciate your time.",
      leaveAnother: "Leave Another Review",
      commentPlaceholder: "Any additional comments?",
      resettingIn: "Resetting in",
      step: "Question %n of 5",
      questions: [
        {
          title: "How was the<br /><em>Cleanliness?</em>",
          sub: "Rate the cleanliness of our facilities",
        },
        {
          title: "How was the<br /><em>Staff Friendliness?</em>",
          sub: "Rate our team's attitude and helpfulness",
        },
        {
          title: "How was the<br /><em>Speed of Service?</em>",
          sub: "Rate how quickly you were served",
        },
        {
          title: "How was the<br /><em>Product Quality?</em>",
          sub: "Rate the quality of what you received",
        },
        {
          title: "How was your<br /><em>Overall Experience?</em>",
          sub: "Rate your overall visit today",
        },
      ],
    },
    fil: {
      veryBad: "Napakasamâ",
      bad: "Masamâ",
      neutral: "Neutral",
      good: "Mabuti",
      excellent: "Napakahusay",
      back: "← Bumalik",
      submitFeedback: "Isumite ang Feedback",
      thankYou: "Salamat!",
      feedbackReceived:
        "Natanggap na ang iyong feedback.\nPinapahalagahan namin ang iyong oras.",
      leaveAnother: "Mag-iwan ng Isa pa",
      commentPlaceholder: "Anumang karagdagang komento?",
      resettingIn: "Nag-re-reset sa loob ng",
      step: "Tanong %n ng 5",
      questions: [
        {
          title: "Kumusta ang<br /><em>Kalinisan?</em>",
          sub: "I-rate ang kalinisan ng aming pasilidad",
        },
        {
          title: "Kumusta ang<br /><em>Kagandahang-loob ng Staff?</em>",
          sub: "I-rate ang ugali at tulong ng aming koponan",
        },
        {
          title: "Kumusta ang<br /><em>Bilis ng Serbisyo?</em>",
          sub: "I-rate kung gaano kabilis kami nagserbisyo",
        },
        {
          title: "Kumusta ang<br /><em>Kalidad ng Produkto?</em>",
          sub: "I-rate ang kalidad ng iyong natanggap",
        },
        {
          title: "Kumusta ang iyong<br /><em>Kabuuang Karanasan?</em>",
          sub: "I-rate ang iyong buong pagbisita ngayon",
        },
      ],
    },
    es: {
      veryBad: "Muy malo",
      bad: "Malo",
      neutral: "Regular",
      good: "Bueno",
      excellent: "Excelente",
      back: "← Atrás",
      submitFeedback: "Enviar Opinión",
      thankYou: "¡Gracias!",
      feedbackReceived: "Tu opinión ha sido recibida.\nApreciamos tu tiempo.",
      leaveAnother: "Dejar Otra Opinión",
      commentPlaceholder: "¿Algún comentario adicional?",
      resettingIn: "Reiniciando en",
      step: "Pregunta %n de 5",
      questions: [
        {
          title: "¿Cómo fue la<br /><em>Limpieza?</em>",
          sub: "Califica la limpieza de nuestras instalaciones",
        },
        {
          title: "¿Cómo fue la<br /><em>Amabilidad del Personal?</em>",
          sub: "Califica la actitud y ayuda de nuestro equipo",
        },
        {
          title: "¿Cómo fue la<br /><em>Velocidad del Servicio?</em>",
          sub: "Califica qué tan rápido fuiste atendido",
        },
        {
          title: "¿Cómo fue la<br /><em>Calidad del Producto?</em>",
          sub: "Califica la calidad de lo que recibiste",
        },
        {
          title: "¿Cómo fue tu<br /><em>Experiencia General?</em>",
          sub: "Califica tu visita de hoy en general",
        },
      ],
    },
    zh: {
      veryBad: "非常差",
      bad: "差",
      neutral: "一般",
      good: "好",
      excellent: "非常好",
      back: "← 返回",
      submitFeedback: "提交反馈",
      thankYou: "谢谢！",
      feedbackReceived: "您的反馈已收到。\n感谢您的时间。",
      leaveAnother: "再次留言",
      commentPlaceholder: "有其他意见吗？",
      resettingIn: "将在以下时间后重置",
      step: "问题 %n / 5",
      questions: [
        { title: "请评价<br /><em>清洁度</em>", sub: "评价我们设施的清洁程度" },
        {
          title: "请评价<br /><em>员工友善度</em>",
          sub: "评价我们团队的态度和帮助",
        },
        { title: "请评价<br /><em>服务速度</em>", sub: "评价我们服务的速度" },
        {
          title: "请评价<br /><em>产品质量</em>",
          sub: "评价您所收到的产品质量",
        },
        {
          title: "请评价<br /><em>整体体验</em>",
          sub: "评价您今天的整体访问体验",
        },
      ],
    },
    ja: {
      veryBad: "とても悪い",
      bad: "悪い",
      neutral: "普通",
      good: "良い",
      excellent: "とても良い",
      back: "← 戻る",
      submitFeedback: "フィードバックを送信",
      thankYou: "ありがとう！",
      feedbackReceived:
        "フィードバックを受け取りました。\nお時間をいただきありがとうございます。",
      leaveAnother: "別のレビューを書く",
      commentPlaceholder: "追加のコメントはありますか？",
      resettingIn: "リセットまで",
      step: "質問 %n / 5",
      questions: [
        {
          title: "<em>清潔さ</em>は<br />いかがでしたか？",
          sub: "施設の清潔さを評価してください",
        },
        {
          title: "<em>スタッフの対応</em>は<br />いかがでしたか？",
          sub: "スタッフの態度や親切さを評価してください",
        },
        {
          title: "<em>サービスの速さ</em>は<br />いかがでしたか？",
          sub: "対応の速さを評価してください",
        },
        {
          title: "<em>商品の品質</em>は<br />いかがでしたか？",
          sub: "受け取った商品の品質を評価してください",
        },
        {
          title: "<em>全体的な体験</em>は<br />いかがでしたか？",
          sub: "今日のご来店全体を評価してください",
        },
      ],
    },
    ar: {
      veryBad: "سيء جداً",
      bad: "سيء",
      neutral: "محايد",
      good: "جيد",
      excellent: "ممتاز",
      back: "رجوع ←",
      submitFeedback: "إرسال الرأي",
      thankYou: "شكراً لك!",
      feedbackReceived: "تم استلام رأيك.\nنقدّر وقتك.",
      leaveAnother: "اترك رأياً آخر",
      commentPlaceholder: "أي تعليقات إضافية؟",
      resettingIn: "إعادة الضبط خلال",
      step: "السؤال %n من 5",
      questions: [
        { title: "كيف كانت<br /><em>النظافة؟</em>", sub: "قيّم نظافة مرافقنا" },
        {
          title: "كيف كانت<br /><em>ودّية الموظفين؟</em>",
          sub: "قيّم موقف وتعاون فريقنا",
        },
        {
          title: "كيف كانت<br /><em>سرعة الخدمة؟</em>",
          sub: "قيّم مدى سرعة خدمتك",
        },
        {
          title: "كيف كانت<br /><em>جودة المنتج؟</em>",
          sub: "قيّم جودة ما تلقيته",
        },
        {
          title: "كيف كانت<br /><em>تجربتك الإجمالية؟</em>",
          sub: "قيّم زيارتك اليوم بشكل عام",
        },
      ],
    },
  };

  // currentLang starts as 'en' and is later overridden by the admin's
  // default_language setting (loaded in applySettings). If the *user*
  // has explicitly chosen a language during this browser session it takes
  // priority, but it resets on a fresh page load so the kiosk always
  // reverts to the admin-configured default between customers.
  let currentLang = "en";
  const _userPickedLang = sessionStorage.getItem("kiosk_lang_user_set");

  function t(key) {
    return (
      (TRANSLATIONS[currentLang] || TRANSLATIONS.en)[key] ||
      TRANSLATIONS.en[key] ||
      key
    );
  }

  function applyTranslations() {
    // Emoji button labels
    const labelKeys = ["veryBad", "bad", "neutral", "good", "excellent"];
    document.querySelectorAll("[data-i18n]").forEach((el) => {
      const key = el.getAttribute("data-i18n");
      if (key) el.textContent = t(key);
    });
    // Textarea placeholder
    document.querySelectorAll("[data-i18n-placeholder]").forEach((el) => {
      const key = el.getAttribute("data-i18n-placeholder");
      if (key) el.placeholder = t(key);
    });
    // RTL support for Arabic
    document.documentElement.dir = currentLang === "ar" ? "rtl" : "ltr";
    // Re-render current step text
    renderStep();
  }

  // ── DOM refs ───────────────────────────────────────────────────────────────
  const ratingRow = document.getElementById("rating-row");
  const commentEl = document.getElementById("comment");
  const charNum = document.getElementById("char-num");
  const submitBtn = document.getElementById("submit-btn");
  const formError = document.getElementById("form-error");
  const feedbackCard = document.getElementById("feedback-card");
  const thankYouCard = document.getElementById("thank-you-card");
  const restartBtn = document.getElementById("restart-btn");
  const questionTitle = document.getElementById("question-title");
  const cardSub = document.getElementById("card-sub");
  const stepIndicator = document.getElementById("step-indicator");
  const wizardFill = document.getElementById("wizard-fill");
  const commentWrap = document.getElementById("comment-wrap");
  const backBtn = document.getElementById("back-btn");
  const nextBtn = document.getElementById("next-btn"); // may be null; nav uses submit+back

  let currentStep = 0;
  let ratings = [0, 0, 0, 0, 0];

  // ── Idle Timer with Countdown ───────────────────────────────────────────────
  const WARN_AT_MS = 30000; // show warning at 30s
  const RESET_AT_MS = 60000; // reset at 60s
  let idleWarnTimer = null;
  let idleResetTimer = null;
  let countdownInterval = null;

  const idleOverlay = document.getElementById("idle-overlay");
  const idleCountdownText = document.getElementById("idle-countdown-text");

  function showIdleWarning() {
    idleOverlay.classList.remove("hidden");
    let remaining = Math.round((RESET_AT_MS - WARN_AT_MS) / 1000);
    idleCountdownText.textContent = `${t("resettingIn")} ${remaining}s…`;
    countdownInterval = setInterval(() => {
      remaining--;
      idleCountdownText.textContent = `${t("resettingIn")} ${remaining}s…`;
      if (remaining <= 0) clearInterval(countdownInterval);
    }, 1000);
  }

  function hideIdleWarning() {
    idleOverlay.classList.add("hidden");
    clearInterval(countdownInterval);
  }

  function resetIdleTimer() {
    clearTimeout(idleWarnTimer);
    clearTimeout(idleResetTimer);
    hideIdleWarning();

    if (!thankYouCard.classList.contains("hidden")) return;
    if (
      currentStep === 0 &&
      ratings.every((r) => r === 0) &&
      !commentEl.value.trim()
    )
      return;

    idleWarnTimer = setTimeout(() => {
      showIdleWarning();
    }, WARN_AT_MS);

    idleResetTimer = setTimeout(() => {
      hideIdleWarning();
      resetForm();
    }, RESET_AT_MS);
  }

  function attachIdleListeners() {
    ["click", "touchstart", "input", "keydown"].forEach((evt) => {
      document.addEventListener(evt, resetIdleTimer, { passive: true });
    });
  }

  // ── Offline Sync ────────────────────────────────────────────────────────────
  const OFFLINE_STORAGE_KEY = "offline_feedback";

  function saveFeedbackOffline(payload) {
    const existing = JSON.parse(
      localStorage.getItem(OFFLINE_STORAGE_KEY) || "[]",
    );
    payload._offline_timestamp = new Date().toISOString();
    existing.push(payload);
    localStorage.setItem(OFFLINE_STORAGE_KEY, JSON.stringify(existing));
  }

  async function syncOfflineFeedback() {
    if (!navigator.onLine) return;
    const existing = JSON.parse(
      localStorage.getItem(OFFLINE_STORAGE_KEY) || "[]",
    );
    if (!existing.length) return;

    const failed = [];
    for (const payload of existing) {
      try {
        const temp = { ...payload };
        delete temp._offline_timestamp;
        const res = await fetch("../api/feedback.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(temp),
          credentials: "same-origin",
        });
        if (!res.ok) failed.push(payload);
      } catch {
        failed.push(payload);
      }
    }

    if (failed.length)
      localStorage.setItem(OFFLINE_STORAGE_KEY, JSON.stringify(failed));
    else localStorage.removeItem(OFFLINE_STORAGE_KEY);
  }

  window.addEventListener("online", syncOfflineFeedback);

  // ── Load Settings & Apply Branding ─────────────────────────────────────────
  // Uses the public kiosk_settings.php endpoint (no admin auth required)
  // so the kiosk page works without being logged in as an admin.
  async function applySettings() {
    try {
      const res = await fetch("../api/kiosk_settings.php");
      if (!res.ok) return;
      const s = await res.json();

      if (s.business_name) {
        const brandEl = document.getElementById("brand-label");
        if (brandEl) brandEl.textContent = s.business_name.toUpperCase();
        document.title = s.business_name + " — Feedback";
      }

      if (s.logo_url) {
        const logoWrap = document.getElementById("kiosk-logo-wrap");
        const logoImg = document.getElementById("kiosk-logo");
        if (logoWrap && logoImg) {
          logoImg.onload = () => {
            logoWrap.style.display = "flex";
          };
          logoImg.onerror = () => {
            logoWrap.style.display = "none";
          };
          // Use root-relative or base-relative path so it resolves correctly.
          // Accept both regular URLs and data URI values.
          const isExternal =
            s.logo_url.startsWith("http") || s.logo_url.startsWith("data:");
          logoImg.src = isExternal ? s.logo_url : "../" + s.logo_url;
          logoImg.alt = (s.business_name || "Business") + " logo";
        }
      }

      // Apply brand primary color (only if admin has explicitly set one)
      if (s.primary_color && s.primary_color.trim()) {
        document.documentElement.style.setProperty("--accent", s.primary_color);
        document.documentElement.style.setProperty(
          "--accent-2",
          s.primary_color + "cc",
        );
        document.documentElement.style.setProperty(
          "--border",
          s.primary_color + "26",
        );
      }

      // ── Apply admin-configured default language ──────────────────────────
      // Only override currentLang if the *user* has NOT explicitly chosen a
      // language during this browser session (sessionStorage flag absent).
      // This means:
      //   - Fresh page load → admin default applies (correct for each new customer)
      //   - User picks a language mid-session → their choice stays until reload
      //   - Admin panel language is completely unaffected
      const validLangs = Object.keys(TRANSLATIONS);
      const adminLang = (s.default_language || "en").trim();
      if (!_userPickedLang && validLangs.includes(adminLang)) {
        currentLang = adminLang;
      } else if (_userPickedLang && validLangs.includes(_userPickedLang)) {
        currentLang = _userPickedLang;
      }
    } catch (err) {
      console.warn("Could not load kiosk settings:", err);
    }
  }

  // ── Wizard Navigation ──────────────────────────────────────────────────────
  function renderStep() {
    clearError();
    const q = (TRANSLATIONS[currentLang] || TRANSLATIONS.en).questions[
      currentStep
    ];
    stepIndicator.textContent = t("step").replace("%n", currentStep + 1);
    questionTitle.innerHTML = q.title;
    cardSub.textContent = q.sub;
    wizardFill.style.width = `${((currentStep + 1) / 5) * 100}%`;

    ratingRow.querySelectorAll(".emoji-btn").forEach((b) => {
      b.classList.remove("selected");
      if (parseInt(b.dataset.value, 10) === ratings[currentStep])
        b.classList.add("selected");
    });

    backBtn.classList.toggle("hidden", currentStep === 0);

    if (currentStep === 4) {
      if (nextBtn) nextBtn.classList.add("hidden");
      submitBtn.classList.remove("hidden");
      commentWrap.classList.remove("hidden");
    } else {
      if (nextBtn) nextBtn.classList.remove("hidden");
      submitBtn.classList.add("hidden");
      commentWrap.classList.add("hidden");
    }

    updateNavState();
  }

  function updateNavState() {
    const hasRating = ratings[currentStep] > 0;
    if (nextBtn) nextBtn.disabled = !hasRating;
    submitBtn.disabled = !hasRating;
  }

  if (nextBtn) {
    nextBtn.addEventListener("click", () => {
      if (currentStep < 4) {
        currentStep++;
        renderStep();
      }
    });
  }

  backBtn.addEventListener("click", () => {
    if (currentStep > 0) {
      currentStep--;
      renderStep();
    }
  });

  // ── Rating Selection ───────────────────────────────────────────────────────
  ratingRow.addEventListener("click", function (e) {
    const btn = e.target.closest(".emoji-btn");
    if (!btn) return;

    ratingRow
      .querySelectorAll(".emoji-btn")
      .forEach((b) => b.classList.remove("selected"));
    btn.classList.add("selected");
    ratings[currentStep] = parseInt(btn.dataset.value, 10);
    if (navigator.vibrate) navigator.vibrate(30);
    updateNavState();

    if (currentStep < 4) {
      setTimeout(() => {
        if (ratings[currentStep] > 0) {
          currentStep++;
          renderStep();
        }
      }, 400);
    }
  });

  // ── Character Counter ──────────────────────────────────────────────────────
  commentEl.addEventListener("input", function () {
    const len = commentEl.value.length;
    charNum.textContent = len;
    charNum.style.color = len >= 270 ? "#d63031" : len >= 200 ? "#e17055" : "";
  });

  // Smooth scroll submit button into view so the virtual keyboard doesn't hide it
  commentEl.addEventListener("focus", function () {
    setTimeout(() => {
      submitBtn.scrollIntoView({ behavior: "smooth", block: "end" });
    }, 300);
  });

  // ── Form Submission ────────────────────────────────────────────────────────
  submitBtn.addEventListener("click", async function () {
    clearError();

    if (ratings.some((r) => r === 0)) {
      showError("Please ensure all 5 questions are answered.");
      return;
    }

    const comment = commentEl.value.trim().slice(0, 300);
    submitBtn.disabled = true;
    submitBtn.querySelector(".btn-text").textContent = "Submitting…";

    const payload = {
      q1_rating: ratings[0],
      q2_rating: ratings[1],
      q3_rating: ratings[2],
      q4_rating: ratings[3],
      q5_rating: ratings[4],
      comment,
      language: currentLang,
    };

    try {
      const res = await fetch("../api/feedback.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
        credentials: "same-origin",
      });
      const data = await res.json();

      if (res.ok && data.success) {
        showThankYou();
      } else {
        showError(data.error || "Something went wrong. Please try again.");
        submitBtn.disabled = false;
        submitBtn.querySelector(".btn-text").textContent = t("submitFeedback");
      }
    } catch (err) {
      saveFeedbackOffline(payload);
      showThankYou();
      submitBtn.disabled = false;
      submitBtn.querySelector(".btn-text").textContent = t("submitFeedback");
    }
  });

  // ── Thank-you Screen ───────────────────────────────────────────────────────
  function showThankYou() {
    feedbackCard.classList.add("hidden");
    thankYouCard.classList.remove("hidden");
    hideIdleWarning();
    setTimeout(resetForm, 8000);
  }

  restartBtn.addEventListener("click", resetForm);

  function resetForm() {
    currentStep = 0;
    ratings = [0, 0, 0, 0, 0];
    commentEl.value = "";
    charNum.textContent = "0";
    charNum.style.color = "";
    ratingRow
      .querySelectorAll(".emoji-btn")
      .forEach((b) => b.classList.remove("selected"));
    clearError();
    submitBtn.disabled = true;
    submitBtn.querySelector(".btn-text").textContent = t("submitFeedback");
    thankYouCard.classList.add("hidden");
    feedbackCard.classList.remove("hidden");

    // Clear the user-chosen language so the next customer always gets the
    // admin-configured default (re-fetch it fresh from settings).
    sessionStorage.removeItem("kiosk_lang_user_set");
    applySettings().then(() => {
      applyTranslations();
      hideIdleWarning();
      clearTimeout(idleWarnTimer);
      clearTimeout(idleResetTimer);
    });
  }

  // ── Helpers ────────────────────────────────────────────────────────────────
  function showError(msg) {
    formError.textContent = msg;
  }
  function clearError() {
    formError.textContent = "";
  }

  // ── Language Picker ────────────────────────────────────────────────────────
  const langToggleBtn = document.getElementById("lang-toggle-btn");
  const langDropdown = document.getElementById("lang-dropdown");
  const langCurrFlag = document.getElementById("lang-current-flag");
  const langCurrLabel = document.getElementById("lang-current-label");

  function setLanguage(lang, isUserChoice = false) {
    if (!TRANSLATIONS[lang]) return;
    currentLang = lang;
    if (isUserChoice) {
      // Persist user-chosen language for the duration of this browser session
      // so it survives iframe reloads but resets when a new session begins
      // (i.e., the next customer gets the admin-configured default).
      sessionStorage.setItem("kiosk_lang_user_set", lang);
    }
    langCurrFlag.textContent = LANGUAGES[lang].flag;
    langCurrLabel.textContent = LANGUAGES[lang].label;
    // Mark active
    document.querySelectorAll(".lang-option").forEach((btn) => {
      btn.classList.toggle("active", btn.dataset.lang === lang);
    });
    langDropdown.classList.remove("open");
    applyTranslations();
  }

  langToggleBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    langDropdown.classList.toggle("open");
  });

  document.querySelectorAll(".lang-option").forEach((btn) => {
    // Pass isUserChoice=true so the user's explicit pick is remembered
    btn.addEventListener("click", () => setLanguage(btn.dataset.lang, true));
  });

  document.addEventListener("click", (e) => {
    if (!document.getElementById("lang-picker").contains(e.target)) {
      langDropdown.classList.remove("open");
    }
  });

  // ── Init ───────────────────────────────────────────────────────────────────
  applySettings().then(() => {
    setLanguage(currentLang);
    attachIdleListeners();
    syncOfflineFeedback();
  });

  // ── Service Worker Registration ────────────────────────────────────────────
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('sw.js').catch(err => {
        console.warn('ServiceWorker registration failed:', err);
      });
    });
  }
})();

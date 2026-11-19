(function () {
  function getApiEndpoint() {
    if (typeof API !== 'undefined' && API) {
      return API.replace(/\/+$/, '') + '/announcement';
    }
    if (typeof getApiBase === 'function') {
      return getApiBase().replace(/\/+$/, '') + '/announcement';
    }
    if (typeof window !== 'undefined' && window.location) {
      const host = window.location.hostname;
      if (host === 'daotomo.site' || host.endsWith('.daotomo.site')) {
        return 'https://daotomo-backend.onrender.com/api/announcement';
      }
    }
    return '/api/announcement';
  }

  async function loadAnnouncement() {
    try {
      const endpoint = getApiEndpoint();
      const res = await fetch(endpoint, { cache: 'no-store' });
      if (!res.ok) return;
      const data = await res.json();
      if (!data || !data.success) return;

      const old = document.getElementById('top-announcement-bar');

      if (!data.enabled) {
        if (old) old.remove();
        document.documentElement.style.setProperty('--announcement-h', '0px');
        return;
      }

      if (sessionStorage.getItem('dismissed_announcement') === data.text) {
        if (old) old.remove();
        document.documentElement.style.setProperty('--announcement-h', '0px');
        return;
      }

      let bar = old;
      if (!bar) {
        bar = document.createElement('div');
        bar.id = 'top-announcement-bar';
        document.body.insertAdjacentElement('afterbegin', bar);
      }

      bar.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 1002;
        background: linear-gradient(90deg, #1e1b4b 0%, #312e81 35%, #4338ca 50%, #312e81 65%, #1e1b4b 100%);
        border-bottom: 1px solid rgba(139, 92, 246, 0.45);
        color: #f8fafc;
        padding: 0.55rem 2.8rem 0.55rem 1.25rem;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.65rem;
        box-shadow: 0 4px 16px rgba(0,0,0,0.45);
        text-align: center;
        flex-wrap: wrap;
        line-height: 1.45;
        transition: transform 0.25s ease;
      `;

      const safeBadge = (data.badge || 'Sự Kiện Open').replace(/[<>&"]/g, '');
      const safeText = (data.text || '').replace(/[<>&"]/g, '');
      const safeLink = (data.link || '/html/Account.html').replace(/[<>&"]/g, '');

      bar.innerHTML = `
        <span style="background: linear-gradient(135deg, #7c3aed, #ec4899); color:#fff; padding: 2px 9px; border-radius: 4px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 2px 8px rgba(124,58,237,0.4); flex-shrink: 0;">${safeBadge}</span>
        <span style="font-weight: 600; color: #f1f5f9;">${safeText}</span>
        ${safeLink ? `<a href="${safeLink}" style="color: #38bdf8; font-weight: 700; text-decoration: underline; text-underline-offset: 3px; display: inline-flex; align-items: center; gap: 2px; white-space: nowrap;">Xem chi tiết ↗</a>` : ''}
        <button id="close-announcement-btn" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:1.25rem;line-height:1;position:absolute;right:1rem;top:50%;transform:translateY(-50%);padding:4px;display:flex;align-items:center;justify-content:center;" title="Đóng thông báo">&times;</button>
      `;

      const updateHeight = () => {
        if (!document.getElementById('top-announcement-bar')) return;
        const h = bar.offsetHeight || 38;
        document.documentElement.style.setProperty('--announcement-h', h + 'px');
      };

      updateHeight();
      window.addEventListener('resize', updateHeight, { passive: true });

      const closeBtn = document.getElementById('close-announcement-btn');
      if (closeBtn) {
        closeBtn.onclick = function () {
          bar.remove();
          document.documentElement.style.setProperty('--announcement-h', '0px');
          sessionStorage.setItem('dismissed_announcement', data.text);
        };
      }
    } catch (e) {
      console.warn('Top announcement load error:', e);
    }
  }

  // Allow external reload (e.g. from Admin settings save)
  window.reloadTopAnnouncement = function (force = true) {
    if (force) {
      sessionStorage.removeItem('dismissed_announcement');
    }
    loadAnnouncement();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadAnnouncement);
  } else {
    loadAnnouncement();
  }
})();

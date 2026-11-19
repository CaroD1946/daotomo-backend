(function () {
  async function loadAnnouncement() {
    try {
      const res = await fetch('/api/announcement');
      if (!res.ok) return;
      const data = await res.json();
      if (!data || !data.success || !data.enabled) return;

      if (sessionStorage.getItem('dismissed_announcement') === data.text) return;

      // Remove existing bar if present
      const old = document.getElementById('top-announcement-bar');
      if (old) old.remove();

      const bar = document.createElement('div');
      bar.id = 'top-announcement-bar';
      bar.style.cssText = `
        background: linear-gradient(90deg, #1e1b4b 0%, #312e81 35%, #4338ca 50%, #312e81 65%, #1e1b4b 100%);
        border-bottom: 1px solid rgba(139, 92, 246, 0.4);
        color: #f8fafc;
        padding: 0.55rem 2.8rem 0.55rem 1.25rem;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.65rem;
        position: relative;
        z-index: 1001;
        box-shadow: 0 4px 16px rgba(0,0,0,0.45);
        text-align: center;
        flex-wrap: wrap;
        line-height: 1.45;
      `;

      const safeBadge = (data.badge || 'Sự Kiện Open').replace(/[<>&"]/g, '');
      const safeText = (data.text || '').replace(/[<>&"]/g, '');
      const safeLink = (data.link || '/html/Account.html').replace(/[<>&"]/g, '');

      bar.innerHTML = `
        <span style="background: linear-gradient(135deg, #7c3aed, #ec4899); color:#fff; padding: 2px 9px; border-radius: 4px; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 2px 8px rgba(124,58,237,0.4);">${safeBadge}</span>
        <span style="font-weight: 600; color: #f1f5f9;">${safeText}</span>
        ${safeLink ? `<a href="${safeLink}" style="color: #38bdf8; font-weight: 700; text-decoration: underline; text-underline-offset: 3px; display: inline-flex; align-items: center; gap: 2px;">Xem chi tiết ↗</a>` : ''}
        <button id="close-announcement-btn" style="background:none;border:none;color:#94a3b8;cursor:pointer;font-size:1.25rem;line-height:1;position:absolute;right:1rem;top:50%;transform:translateY(-50%);padding:4px;" title="Đóng thông báo">&times;</button>
      `;

      document.body.insertAdjacentElement('afterbegin', bar);

      const closeBtn = document.getElementById('close-announcement-btn');
      if (closeBtn) {
        closeBtn.onclick = function () {
          bar.remove();
          sessionStorage.setItem('dismissed_announcement', data.text);
        };
      }
    } catch (e) {
      // Quiet fail if API unavailable
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadAnnouncement);
  } else {
    loadAnnouncement();
  }
})();

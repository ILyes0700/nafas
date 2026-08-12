/* Forces a password change on first login (must_change_password = 1).
   Shows a blocking overlay before the app can be used. */
(function () {
  function needChange() {
    return window.GT_USER && (GT_USER.must_change_password == 1 || GT_USER.must_change_password === true);
  }
  async function csrfToken() {
    const r = await GT.api.get('auth.php', { action: 'csrf' });
    return r.csrf;
  }
  function buildOverlay() {
    const d = document.createElement('div');
    d.id = 'fp-overlay';
    d.setAttribute('style', 'position:fixed;inset:0;z-index:99999;background:rgba(13,59,102,.55);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:16px;');
    d.innerHTML =
      '<div style="background:#fff;max-width:420px;width:100%;border-radius:16px;overflow:hidden;border:1px solid #e6e3da;font-family:Segoe UI,Tahoma,Arial,sans-serif;">' +
        '<div style="background:linear-gradient(135deg,#0d3b66,#1d4e89);padding:20px 24px;color:#fff;">' +
          '<div style="font-size:18px;font-weight:800;">Set a new password</div>' +
          '<div style="font-size:12.5px;color:#cfe0f5;margin-top:4px;">For your security, please choose a new password before continuing.</div>' +
        '</div>' +
        '<div style="padding:22px 24px;">' +
          '<div id="fp-err" style="display:none;background:#fee2e2;color:#991b1b;border-radius:10px;padding:10px 12px;font-size:13px;margin-bottom:12px;"></div>' +
          '<label style="font-size:12.5px;color:#6b7280;font-weight:600;">New password</label>' +
          '<input id="fp-pwd" type="password" style="width:100%;box-sizing:border-box;border:1px solid #e6e3da;background:#f8f7f2;border-radius:10px;padding:10px 12px;margin:5px 0 12px;font:inherit;" placeholder="Min 8 chars, upper + lower + digit">' +
          '<label style="font-size:12.5px;color:#6b7280;font-weight:600;">Confirm password</label>' +
          '<input id="fp-pwd2" type="password" style="width:100%;box-sizing:border-box;border:1px solid #e6e3da;background:#f8f7f2;border-radius:10px;padding:10px 12px;margin:5px 0 16px;font:inherit;" placeholder="Repeat new password">' +
          '<button id="fp-submit" style="width:100%;background:#0d3b66;color:#fff;border:0;border-radius:10px;padding:12px;font-weight:700;cursor:pointer;">Save &amp; continue</button>' +
        '</div>' +
      '</div>';
    return d;
  }
  function show(m) { const e = document.getElementById('fp-err'); if (e) { e.textContent = m; e.style.display = 'block'; } }
  async function submit() {
    const p1 = document.getElementById('fp-pwd').value;
    const p2 = document.getElementById('fp-pwd2').value;
    if ((p1 || '').length < 8) return show('Password must be at least 8 characters.');
    if (!/[A-Z]/.test(p1)) return show('Password must contain an uppercase letter.');
    if (!/[a-z]/.test(p1)) return show('Password must contain a lowercase letter.');
    if (!/[0-9]/.test(p1)) return show('Password must contain a digit.');
    if (p1 !== p2) return show('Passwords do not match.');
    try {
      const csrf = await csrfToken();
      const r = await GT.api.post('auth.php?action=change-own-password', { password: p1, confirm_password: p2, csrf: csrf });
      if (!r.ok) return show(r.error || 'Could not update password.');
      const ov = document.getElementById('fp-overlay'); if (ov) ov.remove();
      if (window.GT_USER) GT_USER.must_change_password = 0;
      window.location.hash = '#/dashboard';
      window.location.reload();
    } catch (e) { show('Request failed. Please try again.'); }
  }
  document.addEventListener('DOMContentLoaded', function () {
    if (!needChange()) return;
    const ov = buildOverlay();
    document.body.appendChild(ov);
    document.getElementById('fp-submit').addEventListener('click', submit);
    document.getElementById('fp-pwd2').addEventListener('keydown', function (e) { if (e.key === 'Enter') submit(); });
  });
})();

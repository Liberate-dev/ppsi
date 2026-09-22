/* =====================================================
   FOOTSTEPSZS — Main JavaScript
   ===================================================== */

document.addEventListener('DOMContentLoaded', function() {

    // ---- 1. NAVBAR SCROLL EFFECT -------------------------
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 30) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        }, { passive: true });
    }

    // ---- 2. HAMBURGER TOGGLE ----------------------------
    const hamburger = document.querySelector('.hamburger');
    const navMenu   = document.querySelector('.nav-menu');
    if (hamburger && navMenu) {
        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('open');
        });
        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) {
                hamburger.classList.remove('active');
                navMenu.classList.remove('open');
            }
        });
    }

    // ---- 3. SCROLL REVEAL ANIMATION ---------------------
    const reveals = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');
    if (reveals.length) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        reveals.forEach(el => observer.observe(el));
    }

    // ---- 4. NOTIFICATION DROPDOWN -----------------------
    const notifBtn = document.getElementById('notifBtn');
    const notifDropdown = document.getElementById('notifDropdown');
    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown.classList.toggle('open');
        });
        document.addEventListener('click', (e) => {
            if (!notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
                notifDropdown.classList.remove('open');
            }
        });
    }

    // ---- 5. ADMIN: POLL NOTIFICATIONS -------------------
    const adminNotifBadge = document.getElementById('adminNotifBadge');
    const sidebarNotifBadge = document.getElementById('sidebarNotifBadge');
    if (adminNotifBadge || sidebarNotifBadge) {
        function pollNotifications() {
            fetch('/api/get_notifications.php')
                .then(r => r.json())
                .then(data => {
                    const count = data.unread || 0;
                    // Update badges
                    [adminNotifBadge, sidebarNotifBadge].forEach(el => {
                        if (!el) return;
                        el.textContent = count;
                        el.style.display = count > 0 ? 'flex' : 'none';
                    });
                    // Update dropdown list
                    const notifList = document.getElementById('notifList');
                    if (notifList && data.notifications) {
                        if (data.notifications.length === 0) {
                            notifList.innerHTML = '<div style="padding:2rem;text-align:center;color:#9CA3AF;font-size:14px;">Tidak ada notifikasi baru.</div>';
                        } else {
                            notifList.innerHTML = data.notifications.map(n => `
                                <div class="notif-item ${n.is_read == 0 ? 'unread' : ''}" onclick="window.location.href='${n.url || '#'}'">
                                    <div class="notif-item-icon">🔔</div>
                                    <div>
                                        <div class="notif-item-text">${n.pesan}</div>
                                        <div class="notif-item-time">${n.time_ago}</div>
                                    </div>
                                </div>
                            `).join('');
                        }
                    }
                })
                .catch(() => {});
        }
        pollNotifications();
        setInterval(pollNotifications, 30000); // every 30 seconds
    }

    // ---- 6. ORDER FORM: DYNAMIC ITEMS (Handled locally by each page to avoid conflicts) --

    // ---- 7. FORM VALIDATION FEEDBACK --------------------
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const btn = form.querySelector('[type=submit]');
            if (btn && !btn.hasAttribute('data-no-loading')) {
                btn.disabled = true;
                btn.innerHTML = '<svg style="animation:spin 1s linear infinite;width:16px;height:16px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> Memproses...';
                setTimeout(() => { btn.disabled = false; }, 8000);
            }
        });
    });

    // ---- 8. ALERT AUTO CLOSE ----------------------------
    document.querySelectorAll('.alert[data-auto-close]').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease, max-height 0.5s ease';
            alert.style.opacity = '0';
            alert.style.maxHeight = '0';
            alert.style.overflow = 'hidden';
            alert.style.padding = '0';
            setTimeout(() => alert.remove(), 600);
        }, 4000);
    });

    // ---- 9. COUNTER ANIMATION (STATS) -------------------
    document.querySelectorAll('[data-count]').forEach(el => {
        const target = parseInt(el.dataset.count);
        const duration = 1500;
        const step = target / (duration / 16);
        let current = 0;
        const timer = setInterval(() => {
            current += step;
            if (current >= target) { current = target; clearInterval(timer); }
            el.textContent = Math.floor(current).toLocaleString('id-ID');
        }, 16);
    });

    // ---- 10. CONFIRM DELETE BUTTONS ---------------------
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!confirm(btn.dataset.confirm)) e.preventDefault();
        });
    });

    // ---- 11. ACTIVE NAV LINK ----------------------------
    const currentPath = window.location.pathname;
    document.querySelectorAll('.nav-menu a, .sidebar-link').forEach(link => {
        const href = link.getAttribute('href') || '';
        if (href && currentPath.endsWith(href.split('?')[0])) {
            link.classList.add('active');
        }
    });
});

/* ---- CSS Spin Keyframe (inline) ----------------------- */
const style = document.createElement('style');
style.textContent = '@keyframes spin { from {transform:rotate(0deg)} to {transform:rotate(360deg)} }';
document.head.appendChild(style);

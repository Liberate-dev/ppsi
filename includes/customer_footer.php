    </main>
</div>
</div>
<script src="/assets/js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('custMobileMenuBtn');
    var sidebar = document.querySelector('.cust-sidebar');
    var overlay = document.getElementById('custSidebarOverlay');
    
    if (btn && sidebar && overlay) {
        btn.addEventListener('click', function() {
            sidebar.classList.add('open');
            overlay.classList.add('active');
        });
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }
});
</script>
</body>
</html>

</div><!-- .admin-wrapper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/university/assets/js/main.js"></script>
<script src="/university/assets/js/api.js"></script>
<script>
// Auto-dismiss flash alerts sau 5 giây
document.querySelectorAll('.auto-dismiss').forEach(function(el) {
    setTimeout(function() {
        var bsAlert = bootstrap.Alert.getOrCreateInstance(el);
        bsAlert.close();
    }, 5000);
});
// Sidebar toggle cho mobile
var sidebarToggle = document.getElementById('sidebarToggle');
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function() {
        document.querySelector('.admin-sidebar').classList.toggle('show');
    });
}
</script>
</body>
</html>

  </main>
</div>

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.body.classList.toggle('sidebar-open');
}
function toggleUserMenu() {
  document.getElementById('userDropdown').classList.toggle('show');
}
// Dışarı tıklayınca user menu kapansın
document.addEventListener('click', function(e) {
  const menu = document.querySelector('.user-menu');
  if (menu && !menu.contains(e.target)) {
    document.getElementById('userDropdown').classList.remove('show');
  }
});
</script>

</body>
</html>

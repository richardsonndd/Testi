document.addEventListener('DOMContentLoaded', function () {
  var sidebarToggle = document.getElementById('sidebarToggle');
  var sidebarClose = document.getElementById('sidebarClose');
  var sidebarBackdrop = document.getElementById('sidebarBackdrop');
  var body = document.body;

  function openSidebar() {
    body.classList.add('sidebar-open');
    sidebarBackdrop.classList.add('visible');
  }

  function closeSidebar() {
    body.classList.remove('sidebar-open');
    sidebarBackdrop.classList.remove('visible');
  }

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', function () {
      if (body.classList.contains('sidebar-open')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });
  }

  if (sidebarClose) {
    sidebarClose.addEventListener('click', closeSidebar);
  }

  if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', closeSidebar);
  }
});
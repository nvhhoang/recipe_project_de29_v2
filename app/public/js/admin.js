document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('sidebar');
  document.getElementById('menuBtn')?.addEventListener('click', () => sidebar?.classList.toggle('open'));

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const message = form.getAttribute('data-confirm') || 'Bạn chắc chắn muốn thực hiện thao tác này?';
      if (!window.confirm(message)) event.preventDefault();
    });
  });
});

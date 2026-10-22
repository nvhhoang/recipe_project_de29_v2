document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.main-nav');
  toggle?.addEventListener('click', () => nav?.classList.toggle('open'));

  document.querySelectorAll('[data-save]').forEach((button) => {
    const key = `recipe-saved-${button.dataset.save}`;
    const saved = localStorage.getItem(key) === '1';
    if (saved) { button.classList.add('saved'); button.textContent = '♥'; }
    button.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      const next = localStorage.getItem(key) !== '1';
      localStorage.setItem(key, next ? '1' : '0');
      button.classList.toggle('saved', next);
      button.textContent = next ? '♥' : '♡';
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      const modal = document.getElementById('recipe-modal');
      if (modal) window.location.href = '/#recipes';
    }
  });
});

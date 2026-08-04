/**
 * Reusable client-side form validation helper (UX enhancement layer).
 */
document.addEventListener('DOMContentLoaded', () => {
  const forms = document.querySelectorAll('form[data-validate]');

  forms.forEach(form => {
    form.addEventListener('submit', (e) => {
      let isValid = true;
      const requiredInputs = form.querySelectorAll('[required]');

      requiredInputs.forEach(input => {
        if (!input.value.trim()) {
          isValid = false;
          input.classList.add('form-control--error');
          let errBox = input.parentNode.querySelector('.form-error');
          if (!errBox) {
            errBox = document.createElement('div');
            errBox.className = 'form-error';
            input.parentNode.appendChild(errBox);
          }
          errBox.textContent = 'This field is required.';
        } else {
          input.classList.remove('form-control--error');
          const errBox = input.parentNode.querySelector('.form-error');
          if (errBox) errBox.remove();
        }
      });

      if (!isValid) {
        e.preventDefault();
      }
    });
  });
});

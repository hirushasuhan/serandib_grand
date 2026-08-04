/**
 * Check-in and Check-out Date Constraint logic.
 */
document.addEventListener('DOMContentLoaded', () => {
  const checkInInput = document.querySelector('input[name="check_in"]');
  const checkOutInput = document.querySelector('input[name="check_out"]');

  if (checkInInput && checkOutInput) {
    const today = new Date().toISOString().split('T')[0];
    if (!checkInInput.hasAttribute('min')) {
      checkInInput.setAttribute('min', today);
    }

    checkInInput.addEventListener('change', () => {
      if (checkInInput.value) {
        const nextDay = new Date(checkInInput.value);
        nextDay.setDate(nextDay.getDate() + 1);
        const minOut = nextDay.toISOString().split('T')[0];
        checkOutInput.setAttribute('min', minOut);

        if (checkOutInput.value && checkOutInput.value <= checkInInput.value) {
          checkOutInput.value = minOut;
        }
      }
    });
  }
});

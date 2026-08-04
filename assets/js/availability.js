/**
 * Live AJAX Availability & Price Quote updates.
 */
document.addEventListener('DOMContentLoaded', () => {
  const quoteForm = document.querySelector('[data-price-quote-form]');
  if (!quoteForm) return;

  const checkIn = quoteForm.querySelector('input[name="check_in"]');
  const checkOut = quoteForm.querySelector('input[name="check_out"]');
  const roomId = quoteForm.querySelector('[name="room_id"]');
  const displayTotal = document.querySelector('[data-quote-total]');
  const displayNights = document.querySelector('[data-quote-nights]');
  const displayTax = document.querySelector('[data-quote-tax]');
  const displayService = document.querySelector('[data-quote-service]');

  async function updateQuote() {
    if (!checkIn.value || !checkOut.value || !roomId || !roomId.value) return;

    try {
      const params = new URLSearchParams({
        room_id: roomId.value,
        check_in: checkIn.value,
        check_out: checkOut.value
      });

      const res = await fetch(`api/price-quote.php?${params.toString()}`);
      if (!res.ok) return;

      const data = await res.json();
      if (data.success) {
        if (displayTotal) displayTotal.textContent = data.formatted_total;
        if (displayNights) displayNights.textContent = `${data.nights} Night(s)`;
        if (displayTax) displayTax.textContent = data.formatted_tax;
        if (displayService) displayService.textContent = data.formatted_service;
      }
    } catch (err) {
      console.error('Failed to fetch price quote', err);
    }
  }

  if (checkIn && checkOut && roomId) {
    checkIn.addEventListener('change', updateQuote);
    checkOut.addEventListener('change', updateQuote);
    if (roomId.tagName === 'SELECT') {
      roomId.addEventListener('change', updateQuote);
    }
  }
});

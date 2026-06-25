document.addEventListener("DOMContentLoaded", () => {
  // ===== КАЛЬКУЛЯТОР =====
  const quantityInput = document.getElementById("quantity");
  const serviceRadios = document.querySelectorAll('input[name="service"]');
  const optionSelect = document.getElementById("optionSelect");
  const propertyCheck = document.getElementById("propertyCheck");
  const optionsBox = document.getElementById("optionsBox");
  const propertyBox = document.getElementById("propertyBox");
  const result = document.getElementById("result");

  const basePrices = {
    type1: 1000,
    type2: 1600,
    type3: 2000,
  };

  function updateVisibility() {
    const selectedType = document.querySelector('input[name="service"]:checked').value;
    if (selectedType === "type1") {
      optionsBox.classList.add("hidden");
      propertyBox.classList.add("hidden");
    } else if (selectedType === "type2") {
      optionsBox.classList.remove("hidden");
      propertyBox.classList.add("hidden");
    } else if (selectedType === "type3") {
      optionsBox.classList.add("hidden");
      propertyBox.classList.remove("hidden");
    }
  }

  function calculateTotal() {
    const type = document.querySelector('input[name="service"]:checked').value;
    const quantity = parseInt(quantityInput.value) || 0;
    let total = basePrices[type] * quantity;

    if (type === "type2") total += parseInt(optionSelect.value) * quantity;
    if (type === "type3" && propertyCheck.checked) total += parseInt(propertyCheck.value) * quantity;

    result.textContent = 'Стоимость: ${total.toLocaleString()} ₽';
  }

  serviceRadios.forEach(radio => {
    radio.addEventListener("change", () => {
      updateVisibility();
      calculateTotal();
    });
  });

  [quantityInput, optionSelect, propertyCheck].forEach(el =>
    el.addEventListener("input", calculateTotal)
  );

  updateVisibility();
  calculateTotal();

  // ===== FORM CARRY =====
  const contactForm = document.getElementById("contactForm");
  const formMessage = document.getElementById("formMessage");

  contactForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(contactForm);
    const submitButton = contactForm.querySelector("button[type='submit']");
    submitButton.disabled = true;
    submitButton.textContent = "Отправка...";

    try {
      const response = await fetch(contactForm.action, {
        method: "POST",
        body: formData,
        headers: { 'Accept': 'application/json' }
      });

      if (response.ok) {
        formMessage.textContent = "Сообщение успешно отправлено!";
        formMessage.style.display = "block";
        formMessage.style.color = "#166534";
        contactForm.reset();
      } else {
        formMessage.textContent = "Произошла ошибка, попробуйте ещё раз.";
        formMessage.style.display = "block";
        formMessage.style.color = "#991b1b";
      }
    } catch (error) {
      formMessage.textContent = "Произошла ошибка, попробуйте ещё раз.";
      formMessage.style.display = "block";
      formMessage.style.color = "#991b1b";
    } finally {
      submitButton.disabled = false;
      submitButton.textContent = "Отправить";
    }
  });
});

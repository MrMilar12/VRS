(() => {
    const classification = document.querySelector('select[name="classification"]');
    if (!classification) return;
    const fields = ['license_number', 'license_expiry'].map(name => document.querySelector('input[name="' + name + '"]'));
    function update() {
        const driver = classification.value === 'Driver';
        fields.forEach(field => {
            field.required = driver;
            field.closest('label').hidden = !driver;
        });
    }
    classification.addEventListener('change', update);
    update();
})();

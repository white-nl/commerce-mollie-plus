function initMollie() {
    if (typeof Mollie === "undefined") {
        setTimeout(initMollie, 200);
    } else {
        var form = document.querySelector('#paymentForm');
        var $wrapper = document.querySelector('.mollie-plus-form');
        var profileId = $wrapper.dataset.profileId;
        var locale = $wrapper.dataset.locale;
        var testMode = $wrapper.dataset.testMode;
        var mollie = Mollie(
            profileId,
            {
                locale: locale,
                testmode: testMode,
            }
        );

        /**
         * Create card holder input
         */
        var cardHolder = mollie.createComponent('cardHolder');
        cardHolder.mount('#card-holder');

        var cardHolderError = document.getElementById("card-holder-error");

        cardHolder.addEventListener("change", function (event) {
            if (event.error && event.touched) {
                cardHolderError.textContent = event.error;
            } else {
                cardHolderError.textContent = "";
            }
        });

        /**
         * Create card number input
         */
        var cardNumber = mollie.createComponent('cardNumber');
        cardNumber.mount('#card-number');

        var cardNumberError = document.getElementById("card-number-error");

        cardNumber.addEventListener("change", function (event) {
            if (event.error && event.touched) {
                cardNumberError.textContent = event.error;
            } else {
                cardNumberError.textContent = "";
            }
        });

        /**
         * Create expiry date input
         */
        var expiryDate = mollie.createComponent('expiryDate');
        expiryDate.mount('#expiry-date');

        var expiryDateError = document.getElementById("expiry-date-error");

        expiryDate.addEventListener("change", function (event) {
            if (event.error && event.touched) {
                expiryDateError.textContent = event.error;
            } else {
                expiryDateError.textContent = "";
            }
        });

        /**
         * Create verification code input
         */
        var verificationCode = mollie.createComponent('verificationCode');
        verificationCode.mount('#verification-code');

        var verificationCodeError = document.getElementById("verification-code-error");

        verificationCode.addEventListener("change", function (event) {
            if (event.error && event.touched) {
                verificationCodeError.textContent = event.error;
            } else {
                verificationCodeError.textContent = "";
            }
        });

        /**
         * Submit handler
         */
        form.addEventListener('submit', async e => {
            e.preventDefault();

            $paymentMethod = document.querySelector('[name="paymentMethod"]');

            if ($paymentMethod.value == 'creditcard') {
                const { token, error } = await mollie.createToken();

                if (error) {
                    console.log(error);
                    return;
                }

                // Add token to the form
                const tokenInput = document.createElement('input');
                tokenInput.setAttribute('type', 'hidden');
                tokenInput.setAttribute('name', 'cardToken');
                tokenInput.setAttribute('value', token);

                form.appendChild(tokenInput);
            }
            // Submit form to the server
            form.submit();
        });
    }
}

initMollie();
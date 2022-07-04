function initMollie() {
    if (typeof Mollie === "undefined") {
        setTimeout(initMollie, 200);
    } else {
        var form = document.querySelector('#paymentForm');
        var $wrapper = document.querySelector('.mollie-plus-form');
        var paymentFormNamespace = $wrapper.dataset.paymentFormNamespace;
        var paymentNamespace = $wrapper.dataset.paymentNamespace;
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

        var options = {
            styles : {
                base: {
                    backgroundColor: '#eee',
                    '::placeholder' : {
                        color: 'rgba(68, 68, 68, 0.2)',
                    }
                },
                invalid: {
                    color: 'rgb(220, 38, 38)',
                }
            }
        };

        /**
         * Create card holder input
         */
        var cardHolder = mollie.createComponent('cardHolder', options);
        cardHolder.mount('#' + paymentNamespace + '-card-holder');

        var cardHolderError = document.getElementById(paymentNamespace + "-card-holder-error");

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
        var cardNumber = mollie.createComponent('cardNumber', options);
        cardNumber.mount('#' + paymentNamespace + '-card-number');

        var cardNumberError = document.getElementById(paymentNamespace + "-card-number-error");

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
        var expiryDate = mollie.createComponent('expiryDate', options);
        expiryDate.mount('#' + paymentNamespace + '-expiry-date');

        var expiryDateError = document.getElementById(paymentNamespace + "-expiry-date-error");

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
        var verificationCode = mollie.createComponent('verificationCode', options);
        verificationCode.mount('#' + paymentNamespace + '-verification-code');

        var verificationCodeError = document.getElementById(paymentNamespace + "-verification-code-error");

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

            $paymentMethod = document.querySelector('[name="' + paymentFormNamespace + '[paymentMethod]"]');

            if ($paymentMethod.value == 'creditcard') {
                const { token, error } = await mollie.createToken();

                if (error) {
                    console.log(error);
                    return;
                }

                // Add token to the form
                const tokenInput = document.createElement('input');
                tokenInput.setAttribute('type', 'hidden');
                tokenInput.setAttribute('name', paymentFormNamespace + '[cardToken]');
                tokenInput.setAttribute('value', token);

                form.appendChild(tokenInput);
            }
            // Submit form to the server
            form.submit();
        });
    }
}

initMollie();
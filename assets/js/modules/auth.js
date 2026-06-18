/**
 * Feature Module: Phone Authentication & Verification
 */

jQuery(document).ready(function ($) {
  const params = kodyt_checkout_params;
  let targetPendingNewPhone = "";

  // Scoped Initialization State Hooks
  window.kodytItiInstance = null;
  window.kodytAccountItiInstance = null;
  window.kodytProfileItiInstance = null;
  window.kodytShippingItiInstance = null;
  window.kodytBillingItiInstance = null;
  let accountOtpTimerInstance = null;

  function runDynamicInitializers() {
    // Read the injected country list array from localization parameters
    const allowedCountries = params.allowed_countries || [];
    // Fallback logic: If 'in' is not in the allowed list, default to the first allowed country, otherwise default to 'in'
    const defaultCountry = allowedCountries.includes("in")
      ? "in"
      : allowedCountries[0] || "in";

    const phoneInput = document.querySelector("#kodyt_auth_phone_active");
    if (phoneInput && !window.kodytItiInstance) {
      window.kodytItiInstance = window.intlTelInput(phoneInput, {
        initialCountry: defaultCountry,
        formatOnDisplay: false,
        numberDisplayFormat: "E164",
        separateDialCode: true,
        onlyCountries: allowedCountries.length ? allowedCountries : undefined,
        utilsScript:
          "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js",
      });
      $(phoneInput).closest(".iti").css("width", "100%");
    }

    const accountPhoneInput = document.querySelector("#kodyt_account_phone");
    if (accountPhoneInput && !window.kodytAccountItiInstance) {
      window.kodytAccountItiInstance = window.intlTelInput(accountPhoneInput, {
        initialCountry: defaultCountry,
        formatOnDisplay: false,
        numberDisplayFormat: "E164",
        separateDialCode: true,
        onlyCountries: allowedCountries.length ? allowedCountries : undefined,
        utilsScript:
          "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js",
      });
      $(accountPhoneInput).closest(".iti").css("width", "100%");
    }

    // ◄ NEW: Initialize international dropdown on the Shipping Phone field
    const shippingPhoneInput = document.querySelector("#kodyt_shipping_phone");
    if (shippingPhoneInput && !window.kodytShippingItiInstance) {
      window.kodytShippingItiInstance = window.intlTelInput(
        shippingPhoneInput,
        {
          initialCountry: defaultCountry,
          formatOnDisplay: false,
          numberDisplayFormat: "E164",
          separateDialCode: true,
          onlyCountries: allowedCountries.length ? allowedCountries : undefined,
          utilsScript:
            "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js",
        },
      );
      $(shippingPhoneInput).closest(".iti").css("width", "100%");
    }

    const billingPhoneInput = document.querySelector("#kodyt_billing_phone");
    if (billingPhoneInput && !window.kodytBillingItiInstance) {
      window.kodytBillingItiInstance = window.intlTelInput(billingPhoneInput, {
        initialCountry: defaultCountry,
        formatOnDisplay: false,
        numberDisplayFormat: "E164",
        separateDialCode: true,
        onlyCountries: allowedCountries.length ? allowedCountries : undefined,
        utilsScript:
          "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js",
      });
      $(billingPhoneInput).closest(".iti").css("width", "100%");
    }
  }

  runDynamicInitializers();
  // Keep your cascading initializer safety hooks active
  setTimeout(runDynamicInitializers, 300);
  setTimeout(runDynamicInitializers, 1200);

  // Profile Screen Verification Injections
  $(document).on("click", "#kodyt-profile-swap-to-input", function (e) {
    e.preventDefault();
    $("#kodyt-profile-phone-interactive-slot").html(`
            <div style="display: flex; gap: 12px; align-items: flex-start; flex-wrap: wrap; width: 100%;">
                <div style="flex: 1; min-width: 260px;">
                    <input type="tel" inputmode="numeric" id="kodyt_profile_phone_active" class="input-text" style="width: 100%; height: 42px; border: var(--kodyt-input-border-width) var(--kodyt-border-style) var(--kodyt-input-border-color); border-radius: var(--kodyt-radius);" placeholder="Enter new mobile number" />
                </div>
                <div>
                    <button type="button" id="kodyt-profile-btn-trigger-action" class="button" style="height: 42px; padding: 0 20px; white-space: nowrap; background-color: var(--kodyt-secondary); color: var(--kodyt-secondary-text); border-radius: var(--kodyt-radius); border: none; font-size: 16px; font-weight: 600;">Verify Code</button>
                </div>
            </div>
        `);

    const liveInput = document.querySelector("#kodyt_profile_phone_active");
    if (liveInput) {
      window.kodytProfileItiInstance = window.intlTelInput(liveInput, {
        initialCountry: "in",
        separateDialCode: true,
        utilsScript:
          "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js",
      });
      $(liveInput).closest(".iti").css("width", "100%");
      liveInput.focus();
    }
  });

  $(document).on("click", "#kodyt-profile-btn-trigger-action", function (e) {
    e.preventDefault();
    let activeInput = $("#kodyt_profile_phone_active");
    if (!activeInput.length || !window.kodytProfileItiInstance) return;

    let rawNumber = activeInput.val().trim();
    if (!rawNumber) return alert("Please enter a valid mobile number.");

    let dialCode =
      window.kodytProfileItiInstance.getSelectedCountryData().dialCode || "";
    targetPendingNewPhone = rawNumber;
    let btn = $(this).prop("disabled", true).text("Sending...");

    $.post(
      params.ajax_url,
      {
        action: "kodyt_proxy_send_otp",
        security: params.checkout_nonce,
        phone: rawNumber,
        country_code: dialCode,
        otp_context: "profile_change",
      },
      function (response) {
        if (response && response.success === true) {
          $("#kodyt-profile-modal-overlay").css("display", "flex");
          $("#kodyt_profile_modal_otp").val("").focus();
        } else {
          alert(
            "Error: " +
              (response.data?.message ||
                "Failed to dispatch verification code."),
          );
        }
        btn.prop("disabled", false).text("Verify Code");
      },
      "json",
    );
  });

  $(document).on(
    "click",
    "#kodyt-profile-modal-close, #kodyt-profile-modal-btn-cancel",
    function (e) {
      $("#kodyt-profile-modal-overlay").css("display", "none");
    },
  );

  $(document).on("click", "#kodyt-profile-modal-btn-verify", function () {
    let token = $("#kodyt_profile_modal_otp").val().trim();
    if (!token) return alert("Please input the verification code.");

    let btn = $(this).prop("disabled", true).text("Processing...");
    let dialCode = window.kodytProfileItiInstance
      ? window.kodytProfileItiInstance.getSelectedCountryData().dialCode
      : "";

    $.post(
      params.ajax_url,
      {
        action: "kodyt_profile_verify_new_phone",
        security: params.checkout_nonce,
        phone: targetPendingNewPhone,
        country_code: dialCode,
        otp: token,
      },
      function (response) {
        if (response && response.success === true) {
          $("#kodyt_profile_phone_hidden").val(targetPendingNewPhone);
          $("#kodyt-profile-phone-interactive-slot").html(`
                    <div style="display: flex; align-items: center; justify-content: space-between; background: var(--kodyt-success); padding: 12px 16px; border: var(--kodyt-step-border-width) var(--kodyt-border-style) var(--kodyt-input-border-color); border-radius: var(--kodyt-radius);">
                        <span style="font-weight: 600; color: var(--kodyt-success-text); font-size: 14px;">+${dialCode} ${targetPendingNewPhone} (Verified)</span>
                    </div>
                `);
          $("#kodyt-profile-modal-overlay").hide();
        } else {
          alert(
            response.data?.message ||
              "The validation code entered is invalid or expired.",
          );
        }
        btn.prop("disabled", false).text("Confirm & Save");
      },
      "json",
    );
  });

  // Checkout Step 1 OTP Trigger Handlers
  $(document).on("click", "#kodyt-btn-send-otp", function () {
    let activeInputTarget = $("#kodyt_auth_phone_active");
    let phoneNum = activeInputTarget.length
      ? activeInputTarget.val().trim()
      : $("#kodyt_auth_phone").val().trim();
    if (!phoneNum) return alert("Please provide a phone number.");

    let btn = $(this).prop("disabled", true).text("Sending...");
    let dialCode = window.kodytItiInstance
      ? window.kodytItiInstance.getSelectedCountryData().dialCode
      : "";

    $.post(
      params.ajax_url,
      {
        action: "kodyt_proxy_send_otp",
        security: params.checkout_nonce,
        phone: phoneNum,
        country_code: dialCode,
        otp_context: "checkout",
      },
      function (response) {
        if (response && response.success === true) {
          $("#kodyt-otp-verify-block").slideDown();
          window.startOtpCountdown();
        } else {
          alert(
            "OTP Error: " + (response.data?.message || "Failed dispatch loop."),
          );
          btn.prop("disabled", false).text("Send OTP");
        }
      },
      "json",
    );
  });

  $(document).on("click", "#kodyt-btn-verify-otp", function () {
    let activeInputTarget = $("#kodyt_auth_phone_active");
    let phoneNum = activeInputTarget.length
      ? activeInputTarget.val().trim()
      : $("#kodyt_auth_phone").val().trim();
    let otpToken = $("#kodyt_otp_code_input").val();
    if (!otpToken) return alert("Please enter your OTP.");

    let btn = $(this);
    btn.prop("disabled", true).text("Checking...");

    let dialCode = window.kodytItiInstance
      ? window.kodytItiInstance.getSelectedCountryData().dialCode
      : "";

    $.post(
      params.ajax_url,
      {
        action: "kodyt_proxy_verify_otp",
        security: params.checkout_nonce,
        phone: phoneNum,
        country_code: dialCode,
        otp: otpToken,
      },
      function (response) {
        if (response && response.success === true) {
          clearInterval(otpTimerInstance);

          if (response.data && response.data.user_id) {
            $("#kodyt_in_memory_user_id").val(response.data.user_id);
          }

          $("#kodyt_auth_phone").val(phoneNum);

          let lockedVerifiedHtml = `
                    <div class="kodyt-verified-phone-container-card">
                        <div class="kodyt-verified-text-details">
                            <span class="kodyt-meta-eyebrow">Verified Customer Profile</span>
                            <span class="kodyt-confirmed-phone-number">+${dialCode} ${phoneNum}</span>
                        </div>
                    </div>
                `;
          $("#kodyt-checkout-phone-interactive-slot").html(lockedVerifiedHtml);

          if (
            response.data &&
            response.data.addresses &&
            response.data.addresses.shipping
          ) {
            let addr = response.data.addresses.shipping;

            let addressHtml = `
                        <div class="kodyt-saved-addresses-wrapper">
                            <p class="kodyt-section-label">Use your saved address records:</p>
                            <div class="kodyt-addresses-grid">
                                <div class="kodyt-address-card selected"
                                    data-fname="${addr.first_name || ""}"
                                    data-lname="${addr.last_name || ""}"
                                    data-email="${addr.email || ""}"
                                    data-sphone="${addr.shipping_phone || ""}"
                                    data-addr1="${addr.address_1 || ""}"
                                    data-addr2="${addr.address_2 || ""}"
                                    data-city="${addr.city || ""}"
                                    data-state="${addr.state || ""}"
                                    data-postcode="${addr.postcode || ""}">
                                    <span class="kodyt-address-type">${addr.type || "Default"}</span>
                                    <strong>${addr.first_name || ""} ${addr.last_name || ""}</strong>
                                    <p>${addr.address_1 || ""}, ${addr.city || ""}, ${addr.state || ""}</p>
                                    <span class="kodyt-badge">Selected</span>
                                </div>
                            </div>
                        </div>`;
            $("#kodyt-saved-addresses-target").html(addressHtml).show();
            $("#kodyt_shipping_address_2").val(addr.address_2 || "");
            $("#kodyt_shipping_first_name").val(addr.first_name || "");
            $("#kodyt_shipping_last_name").val(addr.last_name || "");
            $("#kodyt_shipping_email").val(addr.email || "");
            $("#kodyt_shipping_phone").val(addr.shipping_phone || phoneNum);
            $("#kodyt_shipping_autocomplete").val(addr.address_1 || "");
            $("#kodyt_shipping_city").val(addr.city || "");
            $("#kodyt_shipping_state").val(addr.state || "");
            $("#kodyt_shipping_postcode").val(addr.postcode || "");
          }

          $("#kodyt_shipping_phone").val(phoneNum);

          $("#kodyt-otp-verify-block").slideUp(400);
          $("#kodyt-step-auth").removeClass("active").addClass("completed");
          $("#kodyt-step-shipping").removeClass("locked").addClass("active");

          $("html, body").animate(
            { scrollTop: $("#kodyt-step-shipping").offset().top - 40 },
            600,
          );
          setTimeout(function () {
            $("#kodyt_shipping_autocomplete").focus();
          }, 650);
          return;
        }

        let errorMsg =
          response.data?.message ||
          response.messages ||
          "OTP mismatch. Please try again.";
        alert("Verification rejected: " + errorMsg);
        btn.prop("disabled", false).text("Verify OTP");
      },
      "json",
    ).fail(function () {
      alert("Server error verifying token.");
      btn.prop("disabled", false).text("Verify OTP");
    });
  });

  // Standalone Access Account Login Management
  $(document).on("click", "#kodyt-account-btn-verify-otp", function () {
    let phoneNum = $("#kodyt_auth_phone_active").val();
    let code = $("#kodyt_account_otp_input").val();
    if (!code) return alert("Verification code input value is empty.");

    let btn = $(this).prop("disabled", true).text("Verifying...");
    let dialCode = window.kodytAccountItiInstance
      ? window.kodytAccountItiInstance.getSelectedCountryData().dialCode
      : "";

    $.post(
      params.ajax_url,
      {
        action: "kodyt_account_otp_login",
        security: params.checkout_nonce,
        phone: phoneNum,
        country_code: dialCode,
        otp: code,
      },
      function (response) {
        if (response && response.success === true) {
          window.location.href = response.data.redirect_url;
        } else {
          alert(response.data?.message || "Token mismatch.");
          btn.prop("disabled", false).text("Verify & Access");
        }
      },
      "json",
    );
  });
});

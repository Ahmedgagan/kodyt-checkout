/**
 * Root Controller Orchestrator
 */
// import './utils/helpers.js';
// import './modules/auth.js';
// import './modules/location.js';
// import './modules/marketing.js';

jQuery(document).ready(function ($) {
  const params = kodyt_checkout_params;
  let pinDelayTimeout = null;

  // Custom pipeline event handler: Hydrates addresses during steps processing
  $(document).on("kodyt_auth_address_sync", function (e, addr, phoneNum) {
    $("#kodyt_shipping_first_name").val(addr.first_name || "");
    $("#kodyt_shipping_last_name").val(addr.last_name || "");
    $("#kodyt_shipping_email").val(addr.email || "");
    let targetPhone = addr.shipping_phone || phoneNum;
    $("#kodyt_shipping_phone").val(targetPhone);
    if (window.kodytShippingItiInstance) {
      window.kodytShippingItiInstance.setNumber(targetPhone);
    }
    $("#kodyt_shipping_phone").val(addr.shipping_phone || phoneNum);
    $("#kodyt_shipping_autocomplete").val(addr.address_1 || "");
    $("#kodyt_shipping_house_number").val(addr.hnumber || "");
    $("#kodyt_shipping_city").val(addr.city || "");
    $("#kodyt_shipping_postcode").val(addr.postcode || "");
    $("#kodyt_shipping_country").val(addr.country || "");
  });

  $(document).on("change", "#kodyt_different_billing", function () {
    let block = $("#kodyt-billing-address-block");
    if ($(this).is(":checked")) {
      block.slideDown().find("input").prop("required", true);
    } else {
      block.slideUp().find("input").prop("required", false);
    }
  });

  $(document).on("click", ".kodyt-address-card", function () {
    $(".kodyt-address-card").removeClass("selected");
    $(".kodyt-address-card").find(".kodyt-badge").remove();
    $(this).addClass("selected");

    let d = $(this).data();
    $(this).append('<span class="kodyt-badge">Selected</span>');
    $("#kodyt_shipping_first_name").val(d.fname);
    $("#kodyt_shipping_last_name").val(d.lname);
    $("#kodyt_shipping_email").val(d.email);

    let rawPhone = d.sphone.toString().trim();

    // Safety Check: Format with leading '+' for proper intl-tel-input card parsing
    // if (rawPhone && !rawPhone.startsWith('+')) {
    //     rawPhone = '+' + rawPhone.replace(/^0+/, '');
    // }

    if (window.kodytShippingItiInstance && rawPhone) {
      window.kodytShippingItiInstance.setNumber(rawPhone); // Automatically updates both flag dropdown and field text
    } else {
      $("#kodyt_shipping_phone").val(rawPhone);
    }

    $("#kodyt_shipping_house_number").val(d.hnumber);
    $("#kodyt_shipping_autocomplete").val(d.addr1);
    $("#kodyt_shipping_city").val(d.city);
    $("#kodyt_shipping_postcode").val(d.postcode);
    $("#kodyt_shipping_country").val(d.country);

    if (d.postcode && d.postcode.toString().trim().length >= 6) {
      $("#kodyt_shipping_postcode").trigger("change");
    }
  });

  /* ==========================================================
     KODYT DYNAMIC INLINE PINCODE VALIDATION SYSTEM (BACKEND AJAX)
  ========================================================== */
  function updatePincodeStatusMessage(fieldElement, status, textMessage) {
    if (!fieldElement || fieldElement.length === 0) return;

    const feedbackClass = "kodyt-pin-msg-" + fieldElement.attr("id");
    $("." + feedbackClass).remove(); // Clear historical status lines

    let styles =
      "font-size: 13px; margin-top: 5px; display: block; font-weight: 500; transition: all 0.2s ease;";
    if (status === "success") {
      styles += " color: #16a34a;"; // Emerald Green
    } else if (status === "error") {
      styles += " color: #dc2626;"; // Crimson Red
    } else {
      styles += " color: #6b7280;"; // Slate Grey
    }

    fieldElement.after(
      '<span class="' +
        feedbackClass +
        '" style="' +
        styles +
        '">' +
        textMessage +
        "</span>",
    );
  }

  $(document).on("change", "#kodyt_shipping_postcode", function () {
    const currentField = $(this);
    const pincodeValue = currentField.val().trim();

    if (pincodeValue.length === 0) {
      $(".kodyt-pin-msg-" + currentField.attr("id")).remove();
      return;
    }

    if (pincodeValue.length < 6) {
      updatePincodeStatusMessage(
        currentField,
        "pending",
        "✍️ Typing pincode...",
      );
      return;
    }

    clearTimeout(pinDelayTimeout);
    pinDelayTimeout = setTimeout(function () {
      updatePincodeStatusMessage(currentField, "pending", "🔍 Checking...");

      // Redirect request to your local WordPress server action block
      $.post(
        params.ajax_url,
        {
          action: "kodyt_validate_pincode",
          pincode: pincodeValue,
        },
        function (response) {
          if (
            response &&
            (response.success === true || response.success === "true")
          ) {
            updatePincodeStatusMessage(
              currentField,
              "success",
              `✅ Pincode is valid`,
            );
          } else {
            updatePincodeStatusMessage(
              currentField,
              "error",
              "❌ Pincode is not valid / serviceable",
            );
          }
        },
        "json",
      ).fail(function () {
        // Fail-safe silent clearing on connection drops
        $(".kodyt-pin-msg-" + currentField.attr("id")).remove();
      });
    }, 500);
  });

  $("#kodyt-btn-shipping-mock").on("click", function () {
    if (
      !$("#kodyt_shipping_house_number").val() ||
      !$("#kodyt_shipping_autocomplete").val()
    ) {
      return alert(
        "Shipping House number and Address details are mandatory fields.",
      );
    }
    $("#kodyt-step-shipping").removeClass("active").addClass("completed");
    $("#kodyt-step-payment").removeClass("locked").addClass("active");
  });

  // Form submission processing
  $("#kodyt-custom-checkout-form").on("submit", function (e) {
    e.preventDefault();
    let submitBtn = $("#kodyt-btn-place-order")
      .text("Processing Order...")
      .prop("disabled", true);

    // Extract dial codes from both intl-tel instances
    let authDialCode = window.kodytItiInstance
      ? window.kodytItiInstance.getSelectedCountryData().dialCode
      : "";
    let shippingDialCode = window.kodytShippingItiInstance
      ? window.kodytShippingItiInstance.getSelectedCountryData().dialCode
      : "";
    let billingDialCode = window.kodytBillingItiInstance
      ? window.kodytBillingItiInstance.getSelectedCountryData().dialCode
      : "";

    let searchParams = new URLSearchParams($(this).serialize());
    searchParams.set("kodyt_auth_phone", $("#kodyt_auth_phone").val());
    searchParams.set("kodyt_shipping_phone", $("#kodyt_shipping_phone").val());
    searchParams.set("kodyt_billing_phone", $("#kodyt_billing_phone").val());
    searchParams.set(
      "kodyt_in_memory_user_id",
      $("#kodyt_in_memory_user_id").val(),
    );
    searchParams.set("kodyt_country_dial_code", authDialCode);
    searchParams.set("kodyt_shipping_country_dial_code", shippingDialCode);
    searchParams.set("kodyt_billing_country_dial_code", billingDialCode);

    $.post(
      params.ajax_url,
      {
        action: "kodyt_process_checkout",
        security: params.checkout_nonce,
        form_data: searchParams.toString(),
      },
      function (response) {
        if (
          response &&
          (response.result === "success" || response.success === true)
        ) {
          window.location.href = response.redirect
            ? response.redirect
            : response.data.redirect;
        } else {
          alert(
            "Checkout Error: " +
              $("<div>")
                .html(response.data?.message || "Error details missing.")
                .text(),
          );
          submitBtn.text("Complete Secure Checkout").prop("disabled", false);
        }
      },
      "json",
    ).fail(function () {
      alert("Server error processing checkout.");
      submitBtn.text("Complete Secure Checkout").prop("disabled", false);
    });
  });
});

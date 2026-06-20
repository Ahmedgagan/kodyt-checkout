/**
 * Headless Multi-Step Layout Application Controller Logic
 */
jQuery(document).ready(function ($) {
  const params = kodyt_checkout_params;
  let resendTimerId = null;

  // Navigation back chevron handler
  $(document).on("click", ".kodyt-nav-back-button", function (e) {
    e.preventDefault();
    if ($("#kodyt-flow-screen-two-workspace").is(":visible")) {
      $("#kodyt-flow-screen-two-workspace").hide();
      $("#kodyt-flow-screen-one-auth").fadeIn(150);
    } else {
      $("#kodyt-global-popup-checkout-wrapper").fadeOut(150);
      $("body").css("overflow", "auto");
    }
  });

  // Phone submission handler trigger
  $(document).on("click", "#kodyt-checkout-btn-auth-continue", function (e) {
    e.preventDefault();
    const phoneField = $(
      "#kodyt-checkout-phone-interactive-slot #kodyt_auth_phone_active",
    );

    const rawNumber = phoneField.val().trim();
    if (!rawNumber || rawNumber.length < 10) {
      return alert("Please enter a valid 10-digit mobile number.");
    }

    const dialCode = window.kodytItiInstance
      ? window.kodytItiInstance.getSelectedCountryData().dialCode
      : "91";
    $("#kodyt-target-otp-display-string").text(`+${dialCode} ${rawNumber}`);

    const currentButton = $(this).prop("disabled", true).text("Sending...");

    $.post(
      params.ajax_url,
      {
        action: "kodyt_proxy_send_otp",
        security: params.checkout_nonce,
        phone: rawNumber,
        country_code: dialCode,
        otp_context: "checkout",
      },
      function (response) {
        currentButton.prop("disabled", false).text("Continue");
        if (response && response.success === true) {
          $(".kodyt-otp-digit-cell").val("");
          $("#kodyt-modal-overlay-otp").fadeIn(200);
          $(".kodyt-otp-digit-cell[data-index='1']").focus();
          startModalCountdownTicker();
        } else {
          alert(
            "Error: " + (response.data?.message || "OTP transmission drop."),
          );
        }
      },
      "json",
    );
  });

  // Modal Digit Grid Autofocus Cascading Chain Rules
  $(document).on("keyup", ".kodyt-otp-digit-cell", function (e) {
    const cell = $(this);
    const value = cell.val();
    const currentIndex = parseInt(cell.data("index"));

    if (value.length === 1 && currentIndex < 6) {
      $(
        ".kodyt-otp-digit-cell[data-index='" + (currentIndex + 1) + "']",
      ).focus();
    }

    let fullyCompiledToken = "";
    $(".kodyt-otp-digit-cell").each(function () {
      fullyCompiledToken += $(this).val().trim();
    });

    if (fullyCompiledToken.length === 6) {
      executeTokenAutoVerification(fullyCompiledToken);
    }
  });

  $(document).on("keydown", ".kodyt-otp-digit-cell", function (e) {
    const cell = $(this);
    const currentIndex = parseInt(cell.data("index"));
    if (e.key === "Backspace" && cell.val().length === 0 && currentIndex > 1) {
      $(".kodyt-otp-digit-cell[data-index='" + (currentIndex - 1) + "']")
        .focus()
        .val("");
    }
  });

  // Dynamic Token auto verification loops
  function executeTokenAutoVerification(compiledOtpToken) {
    const phoneField = $(
      "#kodyt-checkout-phone-interactive-slot #kodyt_auth_phone_active",
    );
    const rawNumber = phoneField.val().trim();
    const dialCode = window.kodytItiInstance
      ? window.kodytItiInstance.getSelectedCountryData().dialCode
      : "91";

    $("#kodyt_otp_code_input").val(compiledOtpToken);

    $.post(
      params.ajax_url,
      {
        action: "kodyt_proxy_verify_otp",
        security: params.checkout_nonce,
        phone: rawNumber,
        country_code: dialCode,
        otp: compiledOtpToken,
      },
      function (response) {
        if (response && response.success === true) {
          clearInterval(resendTimerId);
          $("#kodyt-modal-overlay-otp").fadeOut(200);

          if (response.data && response.data.user_id) {
            $("#kodyt_in_memory_user_id").val(response.data.user_id);
          }
          $("#kodyt_auth_phone").val(rawNumber);
          $("#kodyt-session-display-phone-string").text(
            `Logged in using +${dialCode} ${rawNumber}`,
          );

          $("#kodyt-flow-screen-one-auth").hide();
          $("#kodyt-flow-screen-two-workspace").show();

          if (
            response.data &&
            response.data.addresses &&
            (response.data.addresses.shipping ||
              response.data.addresses.billing)
          ) {
            buildDynamicAddressesDrawer(
              response.data.addresses,
              rawNumber,
              dialCode,
            );

            const firstGeneratedCardRow = $(
              "#kodyt-modal-address-drawer-target-stack .kodyt-drawer-address-row-card",
            ).first();
            if (firstGeneratedCardRow.length > 0) {
              applyCardSelectionToHiddenFormInputs(firstGeneratedCardRow);
            }
          } else {
            // Trigger dedicated Address Creation Drawer immediately if zero profile indices exist
            $("#kodyt-address-drawer-action-headline-title").text(
              "Add Shipping Address",
            );
            $(".kodyt-drawer-form-fields-wrapper-context")
              .find(
                'input[type="text"], input[type="tel"], input[type="email"]',
              )
              .val("");
            $("#kodyt_shipping_phone").val(rawNumber);
            $("#kodyt-modal-overlay-address-editor-pane").fadeIn(200);
          }
        } else {
          alert("Verification Rejected: Invalid code.");
          $(".kodyt-otp-digit-cell").val("");
          $(".kodyt-otp-digit-cell[data-index='1']").focus();
        }
      },
      "json",
    );
  }

  function buildDynamicAddressesDrawer(addresses, phoneNum, dialCode) {
    let html = '<div class="kodyt-addresses-vertical-drawer-stack">';

    // Safely check if the shipping array exists and contains elements
    if (
      addresses &&
      addresses.shipping &&
      Array.isArray(addresses.shipping) &&
      addresses.shipping.length > 0
    ) {
      addresses.shipping.forEach(function (addr, index) {
        let isFirst = index === 0;
        let rowClass = isFirst
          ? "kodyt-drawer-address-row-card selected-row-default"
          : "kodyt-drawer-address-row-card";
        let checkedAttr = isFirst ? "checked" : "";

        // Dynamically assign labels based on array index positioning mapping
        let badgeLabel = "Home";
        if (index === 1) {
          badgeLabel = "Office";
        } else if (index > 1) {
          badgeLabel = "Address " + (index + 1);
        }

        // Fall back gracefully to the verified auth phone number if the specific card key is empty
        let sphone = addr.phone || phoneNum;
        if (sphone && !sphone.startsWith("+") && dialCode) {
          // If dialCode is present but number isn't formatted, clean it up
          if (!sphone.startsWith(dialCode)) {
            sphone = "+" + dialCode + sphone;
          } else if (!sphone.startsWith("+")) {
            sphone = "+" + sphone;
          }
        }

        // Clean out duplicates or null parameters from string lines tracking
        let addressParts = [
          addr.address_2,
          addr.address_1,
          addr.city,
          addr.state,
        ].filter(Boolean);

        let addressString = addressParts.join(", ");
        if (addr.postcode) {
          addressString += " - " + addr.postcode;
        }

        html += `
          <div class="${rowClass}"
               data-fname="${addr.first_name || ""}" data-lname="${addr.last_name || ""}"
               data-email="${addr.email || ""}" data-sphone="${sphone}"
               data-addr1="${addr.address_1 || ""}" data-addr2="${addr.address_2 || ""}"
               data-city="${addr.city || ""}" data-state="${addr.state || ""}" data-postcode="${addr.postcode || ""}">
             <div class="kodyt-row-card-right-details" style="position: relative;">
                <div class="kodyt-card-name-row">
                    <strong>${addr.first_name || ""} ${addr.last_name || ""}</strong>
                    <span class="badge-type-home">${badgeLabel}</span>
                    <button type="button" class="kodyt-checkout-edit-address-trigger" title="Edit Address" style="position: absolute; right: 0; top: -2px; background: none; border: none; color: #64748b; font-size: 16px; cursor: pointer; font-weight: bold; padding:0;">⋮</button>
                </div>
                <p>${addressString}</p>
                <span class="card-phone-meta">${sphone}</span>
                <button type="button" class="kodyt-btn-deliver-here-action-trigger" style="margin-top:10px;">Deliver Here</button>
             </div>
          </div>`;
      });
    } else {
      // Safe fallback notice if the dataset structure somehow empties unexpectedly
      html +=
        '<p style="text-align:center; font-size:13px; color:#64748b; padding:20px;">No saved address profiles found.</p>';
    }

    html += "</div>";

    // Inject the structured elements straight across both viewport stack wrappers
    $("#kodyt-modal-address-drawer-target-stack").html(html);
    $("#kodyt-saved-addresses-target").html(html);
  }

  function parseAndSyncExistingAddressesToDrawer() {
    const liveServerRenderedStack = $(
      "#kodyt-custom-checkout-form #kodyt-saved-addresses-target",
    ).html();
    if (liveServerRenderedStack && liveServerRenderedStack.trim() !== "") {
      $("#kodyt-modal-address-drawer-target-stack").html(
        liveServerRenderedStack,
      );
    }
  }

  function updateWorkspaceTopSummaryLabel(fullName, standardLines, rawPhone) {
    $("#kodyt-summary-hydrate-fullname").text(fullName);
    $("#kodyt-summary-hydrate-addresslines").text(standardLines);
    $("#kodyt-summary-hydrate-phonenumber").text(rawPhone);
  }

  function applyCardSelectionToHiddenFormInputs(cardRowElement) {
    const d = cardRowElement.data();
    const nameText = cardRowElement.find("strong").text();
    const addressText = cardRowElement.find("p").text();

    // Determine the array index position of the specific card being clicked
    const activeClickedIndex = cardRowElement.index();

    // 1. UPDATE RAD_BUTTON STATES INSIDE THE FLOATING POPUP MODAL COMPONENT DRAWER
    $("#kodyt-modal-address-drawer-target-stack .kodyt-drawer-address-row-card")
      .removeClass("selected-row-default")
      .find('input[type="radio"]')
      .prop("checked", false);

    const targetModalCardNode = $(
      "#kodyt-modal-address-drawer-target-stack .kodyt-drawer-address-row-card",
    ).eq(activeClickedIndex);
    targetModalCardNode
      .addClass("selected-row-default")
      .find('input[type="radio"]')
      .prop("checked", true);

    // 2. STATE PERSISTENCE FIX: SIMULTANEOUSLY SYNCHRONIZE BACKEND STORAGE DOM TILES
    $("#kodyt-saved-addresses-target .kodyt-drawer-address-row-card")
      .removeClass("selected-row-default")
      .find('input[type="radio"]')
      .prop("checked", false);

    const targetBackendCacheCardNode = $(
      "#kodyt-saved-addresses-target .kodyt-drawer-address-row-card",
    ).eq(activeClickedIndex);
    targetBackendCacheCardNode
      .addClass("selected-row-default")
      .find('input[type="radio"]')
      .prop("checked", true);

    // 3. Hydrate front-facing active layout workspace titles labels strings
    updateWorkspaceTopSummaryLabel(nameText, addressText, d.sphone);

    // 4. Push exact value mappings natively into background WooCommerce variables parameters inputs
    $("#kodyt_shipping_first_name").val(d.fname || "");
    $("#kodyt_shipping_last_name").val(d.lname || "");
    $("#kodyt_shipping_email").val(d.email || "");
    $("#kodyt_shipping_phone").val(d.sphone || "");
    $("#kodyt_shipping_autocomplete").val(d.addr1 || "");
    $("#kodyt_shipping_address_2").val(d.addr2 || "");
    $("#kodyt_shipping_city").val(d.city || "");
    $("#kodyt_shipping_state").val(d.state || "");
    $("#kodyt_shipping_postcode").val(d.postcode || "");

    if (d.postcode && d.postcode.toString().trim().length >= 6) {
      $("#kodyt_shipping_postcode").trigger("change");
    }
  }

  // Address drawer interaction event handlers
  $(document).on(
    "click",
    "#kodyt-checkout-trigger-change-address",
    function (e) {
      e.preventDefault();
      parseAndSyncExistingAddressesToDrawer();
      $("#kodyt-modal-overlay-address-drawer").fadeIn(200);
    },
  );

  $(document).on(
    "click",
    ".kodyt-btn-deliver-here-action-trigger",
    function (e) {
      e.preventDefault();
      e.stopPropagation();
      const rowCard = $(this).closest(".kodyt-drawer-address-row-card");
      applyCardSelectionToHiddenFormInputs(rowCard);
      $("#kodyt-modal-overlay-address-drawer").fadeOut(200);
    },
  );

  // Open creation drawer sheet model
  $(document).on(
    "click",
    "#kodyt-checkout-btn-new-address-toggle",
    function (e) {
      e.preventDefault();
      $("#kodyt-modal-overlay-address-drawer").fadeOut(150);
      $("#kodyt-address-drawer-action-headline-title").text(
        "Add Shipping Address",
      );
      $(".kodyt-drawer-form-fields-wrapper-context")
        .find('input[type="text"], input[type="tel"], input[type="email"]')
        .val("");
      $("#kodyt-modal-overlay-address-editor-pane").fadeIn(200);
    },
  );

  // Open editing drawer sheet model
  $(document).on("click", ".kodyt-checkout-edit-address-trigger", function (e) {
    e.preventDefault();
    e.stopPropagation();
    const parentCardRow = $(this).closest(".kodyt-drawer-address-row-card");
    const d = parentCardRow.data();

    $("#kodyt-modal-overlay-address-drawer").fadeOut(150);
    $("#kodyt-address-drawer-action-headline-title").text(
      "Edit Shipping Address",
    );

    $("#kodyt_shipping_first_name").val(d.fname || "");
    $("#kodyt_shipping_last_name").val(d.lname || "");
    $("#kodyt_shipping_email").val(d.email || "");
    $("#kodyt_shipping_phone").val(d.sphone || "");
    $("#kodyt_shipping_autocomplete").val(d.addr1 || "");
    $("#kodyt_shipping_address_2").val(d.addr2 || "");
    $("#kodyt_shipping_city").val(d.city || "");
    $("#kodyt_shipping_state").val(d.state || "");
    $("#kodyt_shipping_postcode").val(d.postcode || "");

    $("#kodyt-modal-overlay-address-editor-pane").fadeIn(200);
  });

  // Form submit handler inside the editing drawer pane
  $(document).on(
    "click",
    "#kodyt-btn-checkout-save-drawer-address",
    function (e) {
      e.preventDefault();

      // Enforce strict required validations checks
      if (
        !$("#kodyt_shipping_first_name").val() ||
        !$("#kodyt_shipping_address_2").val() ||
        !$("#kodyt_shipping_autocomplete").val()
      ) {
        return alert("Please fill out all mandatory marked (*) fields.");
      }

      const fullName =
        $("#kodyt_shipping_first_name").val() +
        " " +
        $("#kodyt_shipping_last_name").val();
      const addressString =
        $("#kodyt_shipping_address_2").val() +
        ", " +
        $("#kodyt_shipping_autocomplete").val() +
        ", " +
        $("#kodyt_shipping_city").val() +
        ", " +
        $("#kodyt_shipping_state").val() +
        " - " +
        $("#kodyt_shipping_postcode").val();
      const phoneString = $("#kodyt_shipping_phone").val();

      // Hydrate structural labels workspace summaries elements blocks instantly
      updateWorkspaceTopSummaryLabel(fullName, addressString, phoneString);

      $("#kodyt-modal-overlay-address-editor-pane").fadeOut(200);
    },
  );

  // Close loops controls
  $(document).on(
    "click",
    ".kodyt-modal-close-trigger-circle, .kodyt-checkout-modal-overlay",
    function (e) {
      if (
        e.target === this ||
        $(e.target).hasClass("kodyt-modal-close-trigger-circle")
      ) {
        $(".kodyt-checkout-modal-overlay").fadeOut(200);
      }
    },
  );

  $(document).on("click", "#kodyt-back-to-input-phone", function (e) {
    e.preventDefault();
    $("#kodyt-modal-overlay-otp").fadeOut(200);
  });

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
  });

  $(document).on("click", "#kodyt-profile-btn-trigger-action", function (e) {
    e.preventDefault();
    let activeInput = $("#kodyt_profile_phone_active");
    if (!activeInput.length) return;

    let rawNumber = activeInput.val().trim();
    if (!rawNumber) return alert("Please enter a valid mobile number.");

    let dialCode = window.kodytProfileItiInstance
      ? window.kodytProfileItiInstance.getSelectedCountryData().dialCode
      : "91";
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
      : "91";

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

  $(document).on("click", "#kodyt-btn-send-otp", function () {
    let activeInputTarget = $("#kodyt_auth_phone_active");
    let phoneNum = activeInputTarget.length
      ? activeInputTarget.val().trim()
      : $("#kodyt_auth_phone").val().trim();
    if (!phoneNum) return alert("Please provide a phone number.");

    let btn = $(this).prop("disabled", true).text("Sending...");
    let dialCode = window.kodytItiInstance
      ? window.kodytItiInstance.getSelectedCountryData().dialCode
      : "91";

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

  // =========================================================================
  // PHONE SWITCH ENGINE WITH NATURAL COOKIE REFRESH
  // =========================================================================
  $(document).on(
    "click",
    "#kodyt-checkout-change-number-trigger",
    function (e) {
      e.preventDefault();

      const changeBtn = $(this).text("Switching...").prop("disabled", true);

      // 1. Fire the headless logout to drop cookies while cloning the cart into guest space
      $.post(
        kodyt_checkout_params.ajax_url,
        {
          action: "kodyt_headless_ajax_logout",
          security: params.checkout_nonce,
        },
        function (response) {
          if (response && response.success) {
            // 2. Refresh the browser page window instantly with our auto-open parameter tag
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set("kodyt_switch", "1");

            // FIX B: Use replace() instead of href assignment to cleanly clear the window history line
            window.location.replace(currentUrl.toString());
          } else {
            alert(
              "Could not update session parameters. Please reload manually.",
            );
            changeBtn.text("Change number").prop("disabled", false);
          }
        },
        "json",
      ).fail(function () {
        alert("Network timeout updating security session.");
        changeBtn.text("Change number").prop("disabled", false);
      });
    },
  );

  // =========================================================================
  // INSTANT WORKSPACE RESUMPTION FOR CONNECTED USER ACCOUNTS
  // =========================================================================
  $(document).on(
    "click",
    "#kodyt-checkout-btn-logged-in-continue",
    function (e) {
      e.preventDefault();

      // 1. Instantly hide the Screen 1 connection identity block
      $("#kodyt-flow-screen-one-auth").hide();

      // 2. Bring Screen 2 workspace fields back into focus smoothly
      $("#kodyt-flow-screen-two-workspace").fadeIn(200);

      // 3. Re-trigger address calculation rules to ensure fields are populated
      if (typeof window.initializePopupAddressSelection === "function") {
        window.initializePopupAddressSelection();
      }

      // 4. Force synchronization of active components fragments arrays
      $(document).trigger("wc_fragment_refresh");
    },
  );

  // =========================================================================
  // DYNAMIC ADDRESS STACK COMPONENT PRIMING CONTROLLER
  // =========================================================================
  window.initializePopupAddressSelection = function () {
    // Re-parse saved profile addresses out of the fresh template cache
    if (typeof parseAndSyncExistingAddressesToDrawer === "function") {
      parseAndSyncExistingAddressesToDrawer();
    }

    // Scan for the first saved user card row inside the modal drawer container stack
    const absoluteFirstLoadedRowCard = $(
      "#kodyt-modal-address-drawer-target-stack .kodyt-drawer-address-row-card",
    ).first();

    if (absoluteFirstLoadedRowCard.length > 0) {
      // Hydrate the values into the underlying form and update the main display strings
      if (typeof applyCardSelectionToHiddenFormInputs === "function") {
        applyCardSelectionToHiddenFormInputs(absoluteFirstLoadedRowCard);
      }
      $("#kodyt-manual-address-fields-wrapper").hide();
    } else {
      // If the logged-in profile has no configured records, open the fields for manual typing
      if (parseInt($("#kodyt_in_memory_user_id").val() || "0") > 0) {
        $("#kodyt-manual-address-fields-wrapper").show();
      }
    }
  };

  // =========================================================================
  // WOOCOMMERCE CORE AJAX EVENTS INTERCEPTOR WRAPPER
  // =========================================================================

  // Trigger initialization automatically when WooCommerce finishes processing fragments updates
  $(document).on(
    "updated_checkout wc_fragments_refreshed wc_fragments_loaded",
    function () {
      const currentLoggedInUid = parseInt(
        $("#kodyt_in_memory_user_id").val() || "0",
      );

      if (currentLoggedInUid > 0) {
        window.initializePopupAddressSelection();
      }
    },
  );

  // Keep your fallback setup on standard page loads just in case
  if (parseInt($("#kodyt_in_memory_user_id").val() || "0") > 0) {
    setTimeout(function () {
      window.initializePopupAddressSelection();
    }, 200);
  }

  function startModalCountdownTicker() {
    let secondsLeft = 60;
    clearInterval(resendTimerId);
    resendTimerId = setInterval(function () {
      secondsLeft--;
      $("#kodyt-modal-otp-countdown-ticker").text(
        `Resend OTP in 00:${secondsLeft < 10 ? "0" + secondsLeft : secondsLeft}`,
      );
      if (secondsLeft <= 0) {
        clearInterval(resendTimerId);
        $("#kodyt-modal-otp-countdown-ticker").html(
          `<span id="kodyt-trigger-resend-ajax" style="color:#4f46e5; cursor:pointer; font-weight:700;">Resend OTP</span>`,
        );
      }
    }, 1000);
  }

  // Pre-authenticated user loop tracker initialization
  if ($("#kodyt-flow-screen-two-workspace").is(":visible")) {
    parseAndSyncExistingAddressesToDrawer();

    const absoluteFirstLoadedRowCard = $(
      "#kodyt-modal-address-drawer-target-stack .kodyt-drawer-address-row-card",
    ).first();
    if (absoluteFirstLoadedRowCard.length > 0) {
      applyCardSelectionToHiddenFormInputs(absoluteFirstLoadedRowCard);
    } else {
      // Automatically open the creation sheet if a logged-in user somehow has no profile histories saved
      $("#kodyt-address-drawer-action-headline-title").text(
        "Add Shipping Address",
      );
      $("#kodyt-modal-overlay-address-editor-pane").show();
    }
  }
});

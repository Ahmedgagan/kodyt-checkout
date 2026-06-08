/**
 * Shared System Utilities
 */
let otpTimerInstance = null;

window.startOtpCountdown = function startOtpCountdown() {
    let btn = jQuery('#kodyt-btn-send-otp');
    let timeLeft = 60;
    clearInterval(otpTimerInstance);
    btn.prop('disabled', true).addClass('throttled').text(`Resend OTP (${timeLeft}s)`);

    otpTimerInstance = setInterval(function() {
        timeLeft--;
        btn.text(`Resend OTP (${timeLeft}s)`);
        if (timeLeft <= 0) {
            clearInterval(otpTimerInstance);
            btn.prop('disabled', false).removeClass('throttled').text('Send OTP');
        }
    }, 1000);
}

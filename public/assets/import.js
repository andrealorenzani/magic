// Scans a QR code with the camera where the browser can, and puts the text it reads into the import field.
// Pasting always works; without support the scan button stays hidden. Nothing is sent anywhere from here.
(() => {
  const box = document.querySelector("[data-import]");
  if (!box) return;
  const input = box.querySelector('input[name="import"]');
  const scanBtn = box.querySelector("[data-import-scan]");
  const stopBtn = box.querySelector("[data-import-stop]");
  const video = box.querySelector("[data-import-video]");
  const status = box.querySelector("[data-import-status]");
  if (!input) return;
  // A pasted or scanned link replaces the loved person's fields, so their name stops being required.
  const lovedName = document.querySelector('input[name="b_name"]');
  const syncRequired = () => {
    if (lovedName) lovedName.required = input.value.trim() === "";
  };
  input.addEventListener("input", syncRequired);
  syncRequired();
  if (!scanBtn || !stopBtn || !video) return;
  if (!("BarcodeDetector" in window) || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) return;

  const say = (msg) => {
    if (status) status.textContent = msg;
  };
  let stream = null;
  let timer = null;
  let detector = null;

  const stop = () => {
    clearInterval(timer);
    timer = null;
    if (stream) {
      stream.getTracks().forEach((t) => t.stop());
      stream = null;
    }
    video.srcObject = null;
    video.hidden = true;
    stopBtn.hidden = true;
    scanBtn.hidden = false;
  };

  const tick = async () => {
    if (!stream || video.readyState < 2) return;
    try {
      const found = await detector.detect(video);
      if (found.length > 0 && typeof found[0].rawValue === "string" && found[0].rawValue !== "") {
        input.value = found[0].rawValue.slice(0, 600);
        syncRequired();
        stop();
        say("QR code read. Use the button below to see the match results.");
      }
    } catch (e) {
      /* keep trying */
    }
  };

  scanBtn.hidden = false;
  scanBtn.addEventListener("click", async () => {
    say("");
    try {
      detector = new window.BarcodeDetector({ formats: ["qr_code"] });
      stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" }, audio: false });
    } catch (e) {
      stop();
      say("The camera could not be used. You can paste the link instead.");
      return;
    }
    video.srcObject = stream;
    video.hidden = false;
    stopBtn.hidden = false;
    scanBtn.hidden = true;
    try {
      await video.play();
    } catch (e) {
      /* the stream still delivers frames */
    }
    say("Point the camera at the QR code.");
    timer = setInterval(tick, 300);
  });
  stopBtn.addEventListener("click", () => {
    stop();
    say("Scanning stopped.");
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && stream) {
      stop();
      say("Scanning stopped.");
    }
  });
  window.addEventListener("pagehide", stop);
})();

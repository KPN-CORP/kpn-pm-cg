import $ from 'jquery';
import Swal from 'sweetalert2';
window.Swal = Swal;

// ===== Stepper =====
function yearAppraisal() { $("#formYearAppraisal").submit(); }
window.yearAppraisal = yearAppraisal;

$(function () {
  // ---------- STATE ----------
  let currentStep = Number($('.step').data('step')) || 1;
  const totalSteps = $('.form-step').length;

  // File store (baru) + total bytes (existing + baru)
  const fileStore  = new Map(); // key -> File
  let   totalBytes = 0;
  const TEN_MB     = 10 * 1024 * 1024;

  // ---------- HELPERS ----------
  const ERR_MSG = (typeof window !== 'undefined' && window.errorMessages)
    ? String(window.errorMessages) : 'This field is required';

  const fmt = (b)=>!b ? '0 B' : (()=>{const k=1024,s=['B','KB','MB','GB'];const i=Math.floor(Math.log(b)/Math.log(k));return (b/Math.pow(k,i)).toFixed(2)+' '+s[i];})();

  function updateStepper(step) {
    $('.circle').removeClass('active completed').each(function (idx) {
      if (idx < step - 1) $(this).addClass('completed');
      else if (idx === step - 1) $(this).addClass('active');
    });
    $('.form-step').removeClass('active').hide();
    $(`.form-step[data-step="${step}"]`).addClass('active').fadeIn();
    (step === 1) ? $('.prev-btn').hide() : $('.prev-btn').show();
    if (step === totalSteps) { $('.next-btn').hide(); $('.submit-user').show(); }
    else { $('.next-btn').show(); $('.submit-user').hide(); }
  }

  function validateStep(step) {
    let isValid = true, $firstInvalid = null;
    const $scope  = $(`.form-step[data-step="${step}"]`);
    const $fields = $scope.find('.achievement, [required]');

    $fields.each(function(){
      const $el = $(this);
      const val = ($el.val() ?? '').toString().trim();
      const empty = val === '' || val === '-';

      let $fb = $el.siblings('.error-message, .invalid-feedback').first();
      if (!$fb.length) $fb = $('<div class="invalid-feedback error-message"></div>').insertAfter($el);

      if (empty) { $el.addClass('border-danger'); $fb.text(ERR_MSG).show(); isValid = false; if (!$firstInvalid) $firstInvalid = $el; }
      else { $el.removeClass('border-danger'); $fb.text('').hide(); }
    });

    if ($firstInvalid) $firstInvalid.focus();
    return isValid;
  }

  // ---------- NAV ----------
  $('.next-btn').on('click', () => { if (!validateStep(currentStep)) return; currentStep++; updateStepper(currentStep); });
  $('.prev-btn').on('click', () => { currentStep = Math.max(1, currentStep - 1); updateStepper(currentStep); });
  updateStepper(currentStep);

  // ---------- UPLOADER (create & edit) ----------
  const form      = document.getElementById('formAppraisalUser');
  const input     = document.getElementById('attachment_pm'); // <- gunakan id ini di Blade
  const fileWrap  = document.getElementById('fileCards');
  const totalEl   = document.getElementById('totalSizeInfo');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // total awal dari kartu existing (edit)
  function calcExistingBytes() {
    if (!fileWrap) return 0;
    return Array.from(fileWrap.querySelectorAll('.file-card[data-existing="1"]'))
      .reduce((s, el) => s + (Number(el.dataset.size || 0) || 0), 0);
  }
  totalBytes = calcExistingBytes();

  const refreshTotal = () => {
    if (!totalEl) return;
    totalEl.textContent = `Total: ${fmt(totalBytes)} / 10 MB`;
    totalEl.classList.toggle('text-danger', totalBytes > TEN_MB);
  };
  refreshTotal();

  // Tambah file baru
  input?.addEventListener('change', () => {
    const files = Array.from(input.files || []);
    for (const f of files) {
      const key = `${f.name}-${f.size}-${f.lastModified}`;
      if (fileStore.has(key)) continue;
      if (totalBytes + f.size > TEN_MB) { Swal.fire("Total file melebihi 10MB", "", "error"); continue; }
      fileStore.set(key, f);
      totalBytes += f.size;
      addNewCard(key, f.name, f.size, f);
    }
    input.value = ''; // agar pilih file sama tetap trigger change
    refreshTotal();
  });

  // Hapus kartu (existing & baru)
  fileWrap?.addEventListener('click', (e) => {
    const closeBtn = e.target.closest('.btn-close');
    if (!closeBtn) return;

    const card = closeBtn.closest('.file-card');
    if (!card) return;

    const isExisting = card.dataset.existing === '1';
    const size = Number(card.dataset.size || 0) || 0;

    if (isExisting) {
      // hapus hidden keep_files[] (artinya user TIDAK mempertahankan file ini)
      const path = card.dataset.path;
      const hidden = form?.querySelector(`input[name="keep_files[]"][value="${CSS.escape(path)}"]`);
      hidden && hidden.remove();

      totalBytes = Math.max(0, totalBytes - size);
      card.remove();
      refreshTotal();
      return;
    }

    // file baru → remove dari fileStore
    const key = card.dataset.key;
    if (fileStore.has(key)) fileStore.delete(key);
    totalBytes = Math.max(0, totalBytes - size);
    card.remove();
    refreshTotal();
  });

  function addNewCard(key, filename, size, fileObj) {
    const url = URL.createObjectURL(fileObj);

    const $card = $(`
        <div class="file-card d-flex flex-wrap gap-2 align-items-center"
            data-key="${key}" data-size="${size}" data-url="${url}" data-existing="0">
        <span class="d-inline-flex align-items-center gap-1 border rounded-pill p-1 pe-2">
            <a href="${url}" target="_blank" rel="noopener noreferrer"
            class="badge text-bg-warning border-0 rounded-pill px-2 py-1 text-decoration-none"
            style="font-size:.75rem">
            <span class="filename">${filename}</span> <i class="ri-file-text-line"></i>
            </a>
            <button type="button" class="btn-close rounded-circle border-0 p-0 ms-1"
                    title="Remove file" aria-label="Remove file"></button>
        </span>
        </div>
    `);

    // $card.find('.filename').text(filename);

    // Biarkan hanya anchor yg buka file (lebih aksesibel)
    $card.on('click', (e) => {
        if ($(e.target).closest('a, .btn-close').length) return;
        window.open(url, '_blank', 'noopener,noreferrer');
    });

    // Hapus kartu baru
    $card.find('.btn-close').on('click', (e) => {
        e.stopPropagation();
        const sz = Number($card.data('size') || 0);
        if (fileStore.has(key)) fileStore.delete(key);
        totalBytes = Math.max(0, totalBytes - sz);
        URL.revokeObjectURL(url);
        $card.remove();
        refreshTotal(); // sekarang ter-update
    });

    $(fileWrap).append($card);
    }


  // ---------- SUBMIT via AJAX (FormData + fileStore) ----------
  async function submitViaAjax(submitType) {
    if (totalBytes > TEN_MB) { Swal.fire("Total file melebihi 10MB", "", "error"); return; }

    const fd = new FormData(form);            // ambil seluruh field form
    fd.set('submit_type', submitType);        // pastikan submit type
    // lampirkan file baru dari fileStore
    for (const f of fileStore.values()) fd.append('attachment[]', f, f.name);

    const res = await fetch(form.action, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrfToken }, // JANGAN set Content-Type manual!
      body: fd
    });

    if (!res.ok) {
      const txt = await res.text().catch(()=> '');
      throw new Error(txt || 'Request failed');
    }
    return res;
  }

  function toggleBtn($btn, on) {
    const $sp = $btn.find('.spinner-border');
    $btn.toggleClass('disabled', !!on).prop('disabled', !!on);
    if ($sp.length) $sp.toggleClass('d-none', !on);
  }

  // ---------- ACTION BUTTONS ----------
  $('.submit-user').on('click', async function () {
    const submitType = $(this).data('id'); // 'submit_form'
    const step = $(this).data('step'); // 'submit_draft'
    const employeeID = document.getElementById('employee_id').value;
    const userID = document.getElementById('user_id').value;
    
    $('#submitType').val(submitType);
    if (!validateStep(currentStep)) return false;
    let submitMessages = employeeID != userID ? "You can still change it as long as the calibration has not started yet" : "You can still change it as long as the manager has not approved it yet";
    const ok = (await Swal.fire({
      title: "Submit Form?",
      text: submitMessages,
      showCancelButton: true,
      confirmButtonColor: "#3e60d5",
      cancelButtonColor: "#f15776",
      confirmButtonText: "Submit",
      reverseButtons: true,
    })).isConfirmed;
    if (!ok) return false;

    const $btn = $(this);
    try {
      toggleBtn($btn, true);
      await submitViaAjax(submitType);
      Swal.fire({ title: 'Appraisal submitted successfully!', icon: 'success', timer: 1500, showConfirmButton: false });
      if (step === 'review') {
          window.location.href = '/appraisals-task';
        
      } else {
          window.location.href = '/appraisals';
        
      }
    } catch (e) {
      console.error(e);
      Swal.fire('Error', e.message || 'Submit gagal', 'error');
    } finally {
      toggleBtn($btn, false);
    }
    return false;
  });

  $('.submit-draft').on('click', async function () {
    const submitType = $(this).data('id'); // 'submit_draft'
    const step = $(this).data('step'); // 'submit_draft'
    $('#submitType').val(submitType);

    const $btn = $(this);
    try {
      toggleBtn($btn, true);
      await submitViaAjax(submitType);
      Swal.fire({ title: 'Draft saved successfully.', icon: 'success', timer: 1500, showConfirmButton: false });
      if (step === 'review') {
          window.location.href = '/appraisals-task';
        
      } else {
          window.location.href = '/appraisals';
        
      }
    } catch (e) {
      console.error(e);
      Swal.fire('Error', e.message || 'Save draft gagal', 'error');
    } finally {
      toggleBtn($btn, false);
    }
    return false;
  });

  // ---------- Input achievement: hanya angka/-,. ----------
  $('[id^="achievement"]').on('input', function () {
    let v = $(this).val().replace(/[^0-9.-]/g, '');
    if (v.indexOf('-') > 0) v = v.replace('-', '');                 // '-' hanya di awal
    if ((v.match(/\./g) || []).length > 1) v = v.replace(/\.+$/, ''); // satu titik saja
    $(this).val(v);
  });
});

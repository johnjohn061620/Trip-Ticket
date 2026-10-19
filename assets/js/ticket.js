(function () {
  function num(id) {
    var el = document.getElementById(id);
    if (!el) return 0;
    var v = parseFloat(el.value);
    return isNaN(v) ? 0 : v;
  }

  function setVal(id, value) {
    var el = document.getElementById(id);
    if (el) el.value = value;
  }

  function trimNum(r) {
    return String(Math.round(r * 100) / 100);
  }

  function selectedOption() {
    var sel = document.getElementById('vehicle_select');
    if (!sel || !sel.options.length) return null;
    return sel.options[sel.selectedIndex];
  }

  // Average consumption of the selected vehicle (Aerox / Ford Pick up)
  function currentRate() {
    var hid = document.getElementById('fuel_rate');
    if (hid) {
      var r = parseFloat(hid.value);
      if (!isNaN(r) && r > 0) return r;
    }
    var opt = selectedOption();
    if (opt) {
      var ro = parseFloat(opt.getAttribute('data-rate'));
      if (!isNaN(ro) && ro > 0) return ro;
    }
    return 30;
  }

  function updateRateHint() {
    var rate = currentRate();
    var tag = document.getElementById('rate_tag');
    if (tag) tag.textContent = trimNum(rate) + ' km/L';
    var hint = document.getElementById('fuel_rate_hint');
    if (hint) hint.textContent = '= distance \u00F7 ' + trimNum(rate) + ' km/L';
    var hid = document.getElementById('fuel_rate');
    if (hid) hid.value = trimNum(rate);
  }

  // d) Deduct: used during trip = distance travelled / vehicle consumption
  function updateFuelUsed() {
    var elD = document.getElementById('gas_d');
    if (!elD) return;
    var dist = num('speed_dist');
    if (dist > 0) {
      elD.value = Math.round((dist / currentRate()) * 100) / 100;
    }
  }

  // a + b = c ; c - d = e (ending balance, carried to the next trip)
  function calcGas() {
    var elA = document.getElementById('gas_a');
    var elB = document.getElementById('gas_b');
    var elD = document.getElementById('gas_d');
    if (!elA || !elB || !elD) return;

    var a = num('gas_a');
    var b = num('gas_b');
    var d = num('gas_d');
    var c = a + b;
    var hasInput = elA.value !== '' || elB.value !== '' || elD.value !== '';

    setVal('gas_c', hasInput ? c : '');
    setVal('gas_e', hasInput ? Math.round((c - d) * 100) / 100 : '');
  }

  function calcSpeed() {
    var elEnd = document.getElementById('speed_end');
    if (!elEnd) return;
    var start = num('speed_start');
    var end = num('speed_end');

    if (elEnd.value !== '' && end > 0 && end >= start) {
      setVal('speed_dist', end - start);
    } else {
      setVal('speed_dist', '');
    }

    updateFuelUsed();
    calcGas();
  }

  // Trip-log time cells: normalize to "00:00 AM" / "00:00 PM"
  function fmtTime(v) {
    v = String(v || '').trim().toUpperCase();
    if (!v) return '';
    var m = v.match(/^(\d{1,2}):?(\d{2})?\s*(A|P)\.?M?\.?$/);
    if (m) {
      var h = parseInt(m[1], 10);
      var mi = m[2] || '00';
      if (h < 1 || h > 12 || parseInt(mi, 10) > 59) return v;
      return h + ':' + mi + ' ' + m[3] + 'M';
    }
    var m2 = v.match(/^(\d{1,2}):(\d{2})$/);
    if (m2) {
      var h2 = parseInt(m2[1], 10);
      var mi2 = m2[2];
      if (h2 > 23 || parseInt(mi2, 10) > 59) return v;
      var ap = h2 >= 12 ? 'PM' : 'AM';
      var h12 = h2 % 12 || 12;
      return h12 + ':' + mi2 + ' ' + ap;
    }
    return v;
  }

  document.addEventListener('input', function (e) {
    if (!e.target || !e.target.id) return;
    if (['gas_a', 'gas_b'].indexOf(e.target.id) !== -1) calcGas();
    if (['speed_start', 'speed_end'].indexOf(e.target.id) !== -1) calcSpeed();
  });

  document.addEventListener('change', function (e) {
    if (!e.target || !e.target.id) return;
    if (e.target.id === 'driver_select') {
      var name = e.target.value;
      var sig = document.getElementById('driver_sig_line');
      if (sig) sig.textContent = name;
      var cert = document.getElementById('cert_sig_line');
      if (cert) cert.textContent = name;
    }
  });

  document.addEventListener('blur', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('time-input')) {
      e.target.value = fmtTime(e.target.value);
    }
  }, true);

  document.addEventListener('DOMContentLoaded', function () {
    updateRateHint();
    calcSpeed();
    calcGas();
  });

  // Keep empty cells blank on the printed document (placeholder text must not print)
  var phBackup = [];
  var dateTypeBackup = [];
  window.addEventListener('beforeprint', function () {
    phBackup = [];
    var els = document.querySelectorAll('.time-input');
    for (var i = 0; i < els.length; i++) {
      if (els[i].value !== '') continue;
      phBackup.push([els[i], els[i].placeholder]);
      els[i].placeholder = '';
    }
    // Empty date pickers must not print their mm/dd/yyyy format hint
    dateTypeBackup = [];
    var dates = document.querySelectorAll('input[type="date"]');
    for (var j = 0; j < dates.length; j++) {
      if (dates[j].value !== '') continue;
      dateTypeBackup.push(dates[j]);
      dates[j].setAttribute('type', 'text');
    }
  });
  window.addEventListener('afterprint', function () {
    for (var k = 0; k < phBackup.length; k++) {
      phBackup[k][0].placeholder = phBackup[k][1];
    }
    for (var m = 0; m < dateTypeBackup.length; m++) {
      dateTypeBackup[m].setAttribute('type', 'date');
    }
    phBackup = [];
    dateTypeBackup = [];
  });

  var form = document.querySelector('.ticket-form');
  if (form) {
    form.addEventListener('submit', function () {
      var times = document.querySelectorAll('.time-input');
      for (var i = 0; i < times.length; i++) {
        times[i].value = fmtTime(times[i].value);
      }
      var btn = document.querySelector('.print-bar .btn:not(.ghost)');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Saving...';
      }
    });
  }
})();

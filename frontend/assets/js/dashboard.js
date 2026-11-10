(function () {
  const api = window.GraceApi;
  const alertBox = document.querySelector('.dashboard-alert');
  const roleNames = { customer: 'Customer', salonist: 'Salonist', admin: 'Administrator' };
  let currentUser = null;
  let appointments = [];
  let services = [];
  let salonists = [];

  function element(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function valueOf(object, keys, fallback) {
    for (const key of keys) {
      if (object && object[key] !== null && object[key] !== undefined && object[key] !== '') return object[key];
    }
    return fallback;
  }

  function showMessage(message, success) {
    alertBox.textContent = message;
    alertBox.classList.toggle('alert-success', Boolean(success));
    alertBox.hidden = false;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function hideMessage() {
    alertBox.hidden = true;
    alertBox.textContent = '';
    alertBox.classList.remove('alert-success');
  }

  function setButtonBusy(button, busy, label) {
    if (busy) {
      button.dataset.originalText = button.innerHTML;
      button.disabled = true;
      button.textContent = label;
    } else {
      button.disabled = false;
      if (button.dataset.originalText) button.innerHTML = button.dataset.originalText;
    }
  }

  function setActiveView(viewName) {
    const nav = document.querySelector('[data-nav="' + viewName + '"]');
    if (!nav || nav.hidden) return;
    document.querySelectorAll('[data-nav]').forEach(function (item) { item.classList.toggle('active', item === nav); });
    document.querySelectorAll('[data-view]').forEach(function (panel) {
      const active = panel.dataset.view === viewName;
      panel.hidden = !active;
      panel.classList.toggle('active', active);
    });
    const labels = { overview: 'Overview', services: 'Services', appointments: 'Appointments', users: 'People' };
    document.querySelector('[data-page-label]').textContent = labels[viewName] || 'Overview';
    closeMenu();
    window.scrollTo({ top: 0, behavior: 'smooth' });
    if (viewName === 'appointments') loadAppointments();
    if (viewName === 'services') loadServices();
    if (viewName === 'users' && currentUser.role === 'admin') loadUsers();
  }

  function closeMenu() {
    document.querySelector('[data-sidebar]').classList.remove('sidebar-open');
    document.querySelector('[data-scrim]').classList.remove('scrim-visible');
  }

  function configureRole(user) {
    currentUser = user;
    document.querySelector('[data-role-label]').textContent = roleNames[user.role] || 'Your salon';
    document.querySelector('[data-user-name]').textContent = user.name;
    document.querySelector('[data-avatar]').textContent = user.name.trim().charAt(0).toUpperCase() || 'G';
    document.querySelector('[data-welcome-name]').textContent = user.name.trim().split(/\s+/)[0];
    document.querySelector('[data-welcome-copy]').textContent = user.role === 'customer'
      ? 'Take a breath, make a little time for yourself, and let us take care of the rest.'
      : user.role === 'salonist'
        ? 'Here are your assigned visits and a few details to help make each one special.'
        : 'A warm welcome starts behind the scenes. Here is what is happening at your salon.';

    document.querySelectorAll('[data-roles]').forEach(function (item) {
      item.hidden = !item.dataset.roles.split(',').includes(user.role);
    });
    document.querySelector('[data-book-open]').hidden = user.role !== 'customer';
    document.querySelector('[data-add-service]').hidden = user.role !== 'admin';
    document.querySelector('[data-add-salonist]').hidden = user.role !== 'admin';
    document.querySelector('[data-appointment-kicker]').textContent = user.role === 'customer'
      ? 'YOUR SELF-CARE PLANS' : user.role === 'salonist' ? 'YOUR SALON SCHEDULE' : 'SALON SCHEDULE';
    document.querySelector('[data-appointment-copy]').textContent = user.role === 'customer'
      ? 'All your upcoming and past salon visits, in one place.'
      : user.role === 'salonist' ? 'Appointments assigned to you.' : 'Review, assign, and update salon appointments.';
    if (user.role !== 'customer') document.querySelector('[data-book-open]').hidden = true;
  }

  function renderStats() {
    const container = document.querySelector('[data-stats]');
    container.replaceChildren();
    let cards;
    if (currentUser.role === 'customer') {
      cards = [
        ['Your appointments', appointments.filter(function (item) { return !['cancelled', 'completed'].includes(item.status); }).length, 'A little time for you'],
        ['Services to explore', services.length, 'Find a new favorite'],
        ['Next visit', appointments.filter(function (item) { return ['pending', 'confirmed'].includes(item.status); }).length ? 'On the way' : 'Whenever you’re ready', 'We’ll be right here']
      ];
    } else if (currentUser.role === 'salonist') {
      cards = [
        ['Assigned visits', appointments.length, 'On your schedule'],
        ['To confirm', appointments.filter(function (item) { return item.status === 'pending'; }).length, 'Awaiting your review'],
        ['Completed', appointments.filter(function (item) { return item.status === 'completed'; }).length, 'Lovely work']
      ];
    } else {
      cards = [
        ['Appointments', appointments.length, 'Across your salon'],
        ['Customers', window.graceUserCounts ? window.graceUserCounts.customers : '—', 'In your community'],
        ['Salonists', window.graceUserCounts ? window.graceUserCounts.salonists : '—', 'Taking good care']
      ];
    }
    cards.forEach(function (card, index) {
      const article = element('article', 'stat-card');
      article.append(element('span', 'stat-symbol', ['✿', '♡', '✧'][index]));
      article.append(element('span', 'stat-label', card[0]));
      article.append(element('strong', 'stat-value', String(card[1])));
      article.append(element('span', 'stat-note', card[2]));
      container.append(article);
    });
  }

  function formatDate(value) {
    if (!value) return 'Date to be confirmed';
    const date = new Date(String(value).slice(0, 10) + 'T12:00:00');
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }).format(date);
  }

  function formatTime(value) {
    if (!value) return '';
    const pieces = String(value).split(':');
    if (pieces.length < 2) return String(value);
    const date = new Date();
    date.setHours(Number(pieces[0]), Number(pieces[1]), 0, 0);
    return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(date);
  }

  function statusClass(status) {
    return 'status-pill status-' + String(status || 'pending').toLowerCase();
  }

  function updateAppointment(appointmentId, values) {
    return api.patch('appointments.php?id=' + encodeURIComponent(appointmentId), values);
  }

  function makeAppointmentCard(appointment) {
    const card = element('article', 'appointment-card');
    const icon = element('div', 'appointment-date-icon', '✿');
    const details = element('div', 'appointment-details');
    details.append(element('span', 'appointment-label', currentUser.role === 'customer' ? 'YOUR VISIT' : 'SALON VISIT'));
    details.append(element('h3', '', valueOf(appointment, ['service_name'], 'Salon appointment')));
    const person = currentUser.role === 'customer' ? appointment.salonist_name : appointment.customer_name;
    const when = formatDate(appointment.appointment_date) + (appointment.appointment_time ? ' · ' + formatTime(appointment.appointment_time) : '');
    details.append(element('p', 'appointment-when', when + (person ? ' · ' + person : '')));
    if (appointment.notes) details.append(element('p', 'appointment-notes', appointment.notes));
    const status = element('span', statusClass(appointment.status), appointment.status || 'pending');
    card.append(icon, details, status);

    const actions = element('div', 'appointment-actions');
    if (currentUser.role === 'customer' && !['completed', 'cancelled'].includes(appointment.status)) {
      const cancel = element('button', 'small-action action-muted', 'Cancel');
      cancel.type = 'button';
      cancel.addEventListener('click', async function () {
        if (!window.confirm('Cancel this appointment request?')) return;
        cancel.disabled = true;
        try {
          await api.post('appointments.php?id=' + encodeURIComponent(appointment.id), {});
          await refreshDashboard();
          showMessage('Your appointment was cancelled.', true);
        } catch (error) {
          showMessage(error.message);
          cancel.disabled = false;
        }
      });
      actions.append(cancel);
    }

    if (currentUser.role === 'admin' || currentUser.role === 'salonist') {
      const statusSelect = element('select', 'input action-select');
      statusSelect.setAttribute('aria-label', 'Appointment status');
      ['pending', 'confirmed', 'completed', 'cancelled'].forEach(function (value) {
        const option = element('option', '', value.charAt(0).toUpperCase() + value.slice(1));
        option.value = value;
        statusSelect.append(option);
      });
      statusSelect.value = appointment.status;
      actions.append(statusSelect);
      let salonistSelect = null;
      if (currentUser.role === 'admin') {
        salonistSelect = element('select', 'input action-select');
        salonistSelect.setAttribute('aria-label', 'Assign salonist');
        const none = element('option', '', 'Unassigned');
        none.value = '';
        salonistSelect.append(none);
        salonists.forEach(function (salonist) {
          const option = element('option', '', salonist.name);
          option.value = salonist.id;
          salonistSelect.append(option);
        });
        salonistSelect.value = appointment.salonist_id ? String(appointment.salonist_id) : '';
        actions.append(salonistSelect);
      }
      const save = element('button', 'small-action', 'Save');
      save.type = 'button';
      save.addEventListener('click', async function () {
        save.disabled = true;
        const values = { status: statusSelect.value };
        if (salonistSelect) values.salonist_id = salonistSelect.value || null;
        try {
          await updateAppointment(appointment.id, values);
          await refreshDashboard();
          showMessage('Appointment updated.', true);
        } catch (error) {
          showMessage(error.message);
          save.disabled = false;
        }
      });
      actions.append(save);
    }

    if (currentUser.role === 'admin') {
      const remove = element('button', 'small-action action-danger', 'Delete');
      remove.type = 'button';
      remove.addEventListener('click', async function () {
        if (!window.confirm('Permanently delete this appointment?')) return;
        try {
          await api.remove('appointments.php?id=' + encodeURIComponent(appointment.id));
          await refreshDashboard();
          showMessage('Appointment deleted.', true);
        } catch (error) {
          showMessage(error.message);
        }
      });
      actions.append(remove);
    }
    if (actions.childElementCount) card.append(actions);
    return card;
  }

  function renderAppointments() {
    const list = document.querySelector('[data-appointments]');
    const overviewList = document.querySelector('[data-overview-appointments]');
    list.replaceChildren();
    overviewList.replaceChildren();
    const filter = document.querySelector('[data-appointment-filter]').value;
    const visible = appointments.filter(function (item) { return filter === 'all' || item.status === filter; });
    if (!visible.length) {
      list.append(element('div', 'empty-card', currentUser.role === 'customer'
        ? 'No appointments here yet. A little time for yourself is waiting.'
        : 'No appointments match this view.'));
    } else {
      visible.forEach(function (item) { list.append(makeAppointmentCard(item)); });
    }

    const upcoming = appointments.filter(function (item) { return ['pending', 'confirmed'].includes(item.status); }).slice(0, 3);
    if (!upcoming.length) {
      const empty = element('div', 'empty-card appointment-empty');
      empty.append(element('span', 'empty-icon', '♡'));
      const copy = element('div');
      copy.append(element('strong', '', currentUser.role === 'customer' ? 'No appointments just yet' : 'Nothing on the schedule'));
      copy.append(element('p', '', currentUser.role === 'customer' ? 'Whenever you’re ready, your next visit is just a little step away.' : 'New appointments will appear here.'));
      empty.append(copy);
      if (currentUser.role === 'customer') {
        const go = element('button', 'text-button', 'Find a service →');
        go.type = 'button';
        go.addEventListener('click', function () { setActiveView('services'); });
        empty.append(go);
      }
      overviewList.append(empty);
    } else {
      upcoming.forEach(function (item) { overviewList.append(makeAppointmentCard(item)); });
    }
    renderStats();
  }

  function renderServices() {
    const container = document.querySelector('[data-services]');
    const select = document.querySelector('#booking-service');
    const previous = select.value;
    container.replaceChildren();
    select.replaceChildren(element('option', '', 'Choose a service'));
    select.firstChild.value = '';
    if (!services.length) {
      container.append(element('div', 'empty-card', 'Our service menu will be available here soon.'));
      return;
    }
    const symbols = ['✿', '♡', '✧', '❀'];
    services.forEach(function (service, index) {
      const card = element('article', 'service-card');
      const imageUrl = valueOf(service, ['image_url'], '');
      if (typeof imageUrl === 'string' && /^https:\/\/[a-z0-9.-]+\//i.test(imageUrl)) {
        const image = element('img', 'service-image');
        image.src = imageUrl;
        image.alt = '';
        image.loading = 'lazy';
        image.addEventListener('error', function () { image.remove(); });
        card.append(image);
      } else {
        card.append(element('div', 'service-symbol', symbols[index % symbols.length]));
      }
      const top = element('div', 'service-card-top');
      top.append(element('span', 'service-price', '$' + Number(service.price).toFixed(2)));
      card.append(top);
      card.append(element('h2', '', service.name));
      card.append(element('p', 'service-description', service.description || 'A little time just for you.'));
      card.append(element('div', 'service-meta', service.duration_minutes + ' minutes'));
      if (currentUser.role === 'customer') {
        const book = element('button', 'service-book', 'Book this service →');
        book.type = 'button';
        book.addEventListener('click', function () {
          select.value = String(service.id);
          openDialog(document.querySelector('[data-booking-dialog]'));
        });
        card.append(book);
        const option = element('option', '', service.name + ' · $' + Number(service.price).toFixed(2));
        option.value = service.id;
        select.append(option);
      } else if (currentUser.role === 'admin') {
        const remove = element('button', 'service-book action-danger', 'Remove from menu');
        remove.type = 'button';
        remove.addEventListener('click', async function () {
          if (!window.confirm('Remove this service from the booking menu?')) return;
          try {
            await api.remove('services.php?id=' + encodeURIComponent(service.id));
            await refreshDashboard();
            showMessage('Service removed from the menu.', true);
          } catch (error) {
            showMessage(error.message);
          }
        });
        card.append(remove);
      }
      container.append(card);
    });
    if (previous && Array.from(select.options).some(function (option) { return option.value === previous; })) {
      select.value = previous;
    }
  }

  function renderUsers(users) {
    const container = document.querySelector('[data-users]');
    container.replaceChildren();
    window.graceUserCounts = {
      customers: users.filter(function (user) { return user.role === 'customer'; }).length,
      salonists: users.filter(function (user) { return user.role === 'salonist'; }).length
    };
    renderStats();
    if (!users.length) {
      container.append(element('div', 'empty-card', 'No accounts match this filter.'));
      return;
    }
    users.forEach(function (user) {
      const card = element('article', 'person-card');
      const avatar = element('span', 'avatar person-avatar', user.name.trim().charAt(0).toUpperCase());
      const details = element('div', 'person-details');
      details.append(element('strong', '', user.name));
      details.append(element('span', '', user.email));
      if (user.phone) details.append(element('span', '', user.phone));
      card.append(avatar, details, element('span', 'role-pill', user.role));
      const remove = element('button', 'small-action action-danger', 'Delete');
      remove.type = 'button';
      remove.addEventListener('click', async function () {
        if (!window.confirm('Delete ' + user.name + '\'s account?')) return;
        try {
          await api.remove('users.php?id=' + encodeURIComponent(user.id));
          await loadUsers();
          showMessage('User deleted.', true);
        } catch (error) {
          showMessage(error.message);
        }
      });
      card.append(remove);
      container.append(card);
    });
  }

  async function loadUsers() {
    const role = document.querySelector('[data-people-filter]').value;
    const payload = await api.get('users.php' + (role ? '?role=' + encodeURIComponent(role) : ''));
    const users = api.list(payload, 'users');
    salonists = users.filter(function (user) { return user.role === 'salonist'; });
    renderUsers(users);
  }

  async function loadServices() {
    const payload = await api.get('services.php');
    services = api.list(payload, 'services');
    renderServices();
    renderStats();
  }

  async function loadAppointments() {
    const list = document.querySelector('[data-appointments]');
    list.replaceChildren();
    list.append(element('div', 'loading-card', 'Loading appointments…'));
    const payload = await api.get('appointments.php');
    appointments = api.list(payload, 'appointments');
    renderAppointments();
  }

  async function refreshDashboard() {
    hideMessage();
    const results = await Promise.allSettled([loadAppointments(), loadServices()]);
    const failed = results.find(function (result) { return result.status === 'rejected'; });
    if (failed) throw failed.reason;
    if (currentUser.role === 'admin') await loadUsers();
  }

  function openDialog(dialog) {
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
  }

  function closeDialog(dialog) {
    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');
  }

  async function submitForm(form, endpoint, successMessage, afterSuccess) {
    const button = form.querySelector('[type="submit"]');
    const alert = form.querySelector('[data-alert]');
    if (!form.reportValidity()) return;
    alert.hidden = true;
    setButtonBusy(button, true, 'Please wait…');
    try {
      await api.post(endpoint, Object.fromEntries(new FormData(form).entries()));
      closeDialog(form.closest('dialog'));
      form.reset();
      if (afterSuccess) await afterSuccess();
      showMessage(successMessage, true);
    } catch (error) {
      alert.textContent = error.message || 'The request could not be completed.';
      alert.hidden = false;
    } finally {
      setButtonBusy(button, false);
    }
  }

  document.querySelectorAll('[data-nav]').forEach(function (button) {
    button.addEventListener('click', function () { setActiveView(button.dataset.nav); });
  });
  document.querySelectorAll('[data-go]').forEach(function (button) {
    button.addEventListener('click', function () { setActiveView(button.dataset.go); });
  });
  document.querySelector('[data-appointment-filter]').addEventListener('change', renderAppointments);
  document.querySelector('[data-people-filter]').addEventListener('change', function () {
    loadUsers().catch(function (error) { showMessage(error.message); });
  });

  const sidebar = document.querySelector('[data-sidebar]');
  const scrim = document.querySelector('[data-scrim]');
  document.querySelector('[data-menu-toggle]').addEventListener('click', function () {
    sidebar.classList.toggle('sidebar-open');
    scrim.classList.toggle('scrim-visible');
  });
  scrim.addEventListener('click', closeMenu);

  const bookingDialog = document.querySelector('[data-booking-dialog]');
  document.querySelector('[data-book-open]').addEventListener('click', function () { openDialog(bookingDialog); });
  document.querySelector('[data-book-close]').addEventListener('click', function () { closeDialog(bookingDialog); });
  bookingDialog.addEventListener('click', function (event) { if (event.target === bookingDialog) closeDialog(bookingDialog); });
  const today = new Date();
  document.querySelector('#booking-date').min = [today.getFullYear(), String(today.getMonth() + 1).padStart(2, '0'), String(today.getDate()).padStart(2, '0')].join('-');
  document.querySelector('[data-booking-form]').addEventListener('submit', function (event) {
    event.preventDefault();
    submitForm(event.currentTarget, 'appointments.php', 'Your appointment request has been sent.', refreshDashboard);
  });

  const serviceDialog = document.querySelector('[data-service-dialog]');
  document.querySelector('[data-add-service]').addEventListener('click', function () { openDialog(serviceDialog); });
  document.querySelector('[data-service-close]').addEventListener('click', function () { closeDialog(serviceDialog); });
  document.querySelector('[data-service-form]').addEventListener('submit', function (event) {
    event.preventDefault();
    submitForm(event.currentTarget, 'services.php', 'Service added to the menu.', refreshDashboard);
  });

  const salonistDialog = document.querySelector('[data-salonist-dialog]');
  document.querySelector('[data-add-salonist]').addEventListener('click', function () { openDialog(salonistDialog); });
  document.querySelector('[data-salonist-close]').addEventListener('click', function () { closeDialog(salonistDialog); });
  document.querySelector('[data-salonist-form]').addEventListener('submit', function (event) {
    event.preventDefault();
    submitForm(event.currentTarget, 'users.php', 'Salonist account created.', async function () {
      await loadUsers();
    });
  });

  document.querySelector('[data-logout]').addEventListener('click', async function () {
    const button = document.querySelector('[data-logout]');
    button.disabled = true;
    try {
      await api.post('logout.php', {});
      sessionStorage.removeItem('grace_csrf_token');
      window.location.href = 'index.html';
    } catch (error) {
      showMessage(error.message || 'Unable to sign out. Please try again.');
      button.disabled = false;
    }
  });

  async function initialize() {
    try {
      const response = await api.get('me.php');
      const data = api.unwrap(response);
      configureRole(data.user);
      if (currentUser.role === 'admin') {
        const usersData = await api.get('users.php');
        const people = api.list(usersData, 'users');
        salonists = people.filter(function (person) { return person.role === 'salonist'; });
        window.graceUserCounts = {
          customers: people.filter(function (person) { return person.role === 'customer'; }).length,
          salonists: salonists.length
        };
        renderUsers(people);
      }
      await Promise.all([loadAppointments(), loadServices()]);
    } catch (error) {
      if (error.status === 401) {
        window.location.href = 'index.html';
        return;
      }
      showMessage(error.message || 'We could not load your salon details. Please refresh the page.');
      document.querySelector('[data-appointments]').replaceChildren(element('div', 'empty-card', 'Appointments are temporarily unavailable.'));
      document.querySelector('[data-overview-appointments]').replaceChildren(element('div', 'empty-card', 'Please check the server connection and refresh.'));
      document.querySelector('[data-services]').replaceChildren(element('div', 'empty-card', 'Services are temporarily unavailable.'));
    }
  }

  initialize();
})();
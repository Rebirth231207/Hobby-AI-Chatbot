/**
 * اسکریپت اصلی پنل مدیریت پشتیبانی هوشمند (SPA).
 *
 * جاوااسکریپت کاملاً خالص (Vanilla JS) بدون هیچ وابستگی به فریمورک،
 * طبق محدودیت‌های سخت‌گیرانه پروژه. تمام تعامل‌ها (جابجایی بین بخش‌ها،
 * چت زنده با polling، مدیریت تیکت، CRUD نالج گراف/FAQ/پیام آماده،
 * تنظیمات) در همین فایل پیاده‌سازی شده‌اند.
 */

(function () {
	'use strict';

	/* =========================================================================
	 * وضعیت کلی برنامه (State)
	 * ========================================================================= */

	var state = {
		activeSection: 'liveChat',
		isDark: true,
		unreadFilter: false,
		selectedConversationId: null,
		conversationPollTimer: null,
		lastAdminMessageId: 0,
		cannedResponses: [],
		faqSubTab: 'faq',
		settingsTab: 'ui',
		responseMode: 'human',
		ticketView: 'list',
		selectedTicketId: null,
		modalContext: null, // { type: 'knowledge'|'faq'|'canned', id: number|null }
		mediaTarget: null // کلید تنظیمات ظاهری که رسانه انتخابی باید در آن قرار گیرد
	};

	/* =========================================================================
	 * توابع کمکی ارتباط با REST API
	 * ========================================================================= */

	/**
	 * ارسال درخواست JSON به REST API افزونه با هدر نانس امنیتی.
	 *
	 * @param {string} endpoint مسیر نسبی endpoint.
	 * @param {string} method متد HTTP.
	 * @param {Object|null} bodyData داده‌های ارسالی (برای POST/PUT).
	 * @return {Promise<Object>}
	 */
	function ssAdminApi(endpoint, method, bodyData) {
		var url = SS_Admin_Data.rest_url + endpoint;

		var options = {
			method: method,
			headers: {
				'X-WP-Nonce': SS_Admin_Data.nonce
			}
		};

		if (bodyData && (method === 'POST' || method === 'PUT')) {
			options.headers['Content-Type'] = 'application/json';
			options.body = JSON.stringify(bodyData);
		}

		return fetch(url, options).then(function (response) {
			return response.json().then(function (data) {
				if (!response.ok) {
					var errMessage = (data && data.message) ? data.message : 'خطای ارتباط با سرور رخ داد.';
					throw new Error(errMessage);
				}
				return data;
			});
		});
	}

	/**
	 * فرار دادن (Escape) امن یک رشته متنی جهت درج در innerHTML و جلوگیری از XSS.
	 *
	 * @param {string} text متن خام.
	 * @return {string}
	 */
	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text === null || text === undefined ? '' : String(text);
		return div.innerHTML;
	}

	/* =========================================================================
	 * سیستم اعلان Toast
	 * ========================================================================= */

	/**
	 * نمایش یک اعلان کوتاه (Toast) در پایین صفحه.
	 *
	 * @param {string} message متن پیام.
	 * @param {string} type نوع پیام ('success' یا 'error').
	 * @return {void}
	 */
	function showToast(message, type) {
		var container = document.getElementById('ss-toast-container');
		var toast = document.createElement('div');
		toast.className = 'ss-toast ' + (type === 'error' ? 'error' : 'success');
		toast.textContent = message;
		container.appendChild(toast);

		requestAnimationFrame(function () {
			toast.classList.add('show');
		});

		setTimeout(function () {
			toast.classList.remove('show');
			setTimeout(function () {
				if (toast.parentNode) {
					toast.parentNode.removeChild(toast);
				}
			}, 300);
		}, 3500);
	}

	/**
	 * نمایش استاندارد خطا از یک Promise رد‌شده (catch) به صورت toast.
	 *
	 * @param {Error} error شیء خطا.
	 * @return {void}
	 */
	function handleApiError(error) {
		showToast(error && error.message ? error.message : 'خطایی رخ داد.', 'error');
	}

	/* =========================================================================
	 * تغییر پوسته تیره/روشن
	 * ========================================================================= */

	function initTheme() {
		var root = document.getElementById('ss-admin-root');
		var toggleBtn = document.getElementById('ss-theme-toggle');
		var iconSun = document.getElementById('ss-icon-sun');
		var iconMoon = document.getElementById('ss-icon-moon');

		toggleBtn.addEventListener('click', function () {
			state.isDark = !state.isDark;
			applyTheme(root, iconSun, iconMoon);
		});

		applyTheme(root, iconSun, iconMoon);
	}

	/**
	 * اعمال کلاس پوسته روی ریشه پنل و جابجایی آیکون خورشید/ماه.
	 *
	 * @param {HTMLElement} root المان ریشه پنل.
	 * @param {HTMLElement} iconSun آیکون خورشید.
	 * @param {HTMLElement} iconMoon آیکون ماه.
	 * @return {void}
	 */
	function applyTheme(root, iconSun, iconMoon) {
		if (state.isDark) {
			root.classList.remove('ss-light');
			iconSun.style.display = '';
			iconMoon.style.display = 'none';
		} else {
			root.classList.add('ss-light');
			iconSun.style.display = 'none';
			iconMoon.style.display = '';
		}
	}

	/* =========================================================================
	 * ناوبری بین بخش‌های اصلی (سایدبار)
	 * ========================================================================= */

	function initNavigation() {
		var navItems = document.querySelectorAll('.ss-nav-item');

		navItems.forEach(function (item) {
			item.addEventListener('click', function () {
				var section = item.getAttribute('data-section');
				switchSection(section);
			});
		});
	}

	/**
	 * جابجایی به یک بخش اصلی و بارگذاری داده‌های مربوط به آن در صورت نیاز.
	 *
	 * @param {string} section نام بخش ('liveChat', 'tickets', 'knowledge', 'faqs', 'settings').
	 * @return {void}
	 */
	function switchSection(section) {
		state.activeSection = section;

		document.querySelectorAll('.ss-nav-item').forEach(function (item) {
			item.classList.toggle('active', item.getAttribute('data-section') === section);
		});

		var sectionIds = ['liveChat', 'tickets', 'knowledge', 'faqs', 'settings'];
		sectionIds.forEach(function (id) {
			var el = document.getElementById('ss-section-' + id);
			el.classList.toggle('active', id === section);
		});

		// توقف polling چت زنده هنگام خروج از آن بخش، جهت صرفه‌جویی منابع
		if (section !== 'liveChat') {
			stopConversationPolling();
		}

		// بارگذاری داده مربوط به بخش انتخاب‌شده
		if (section === 'liveChat') {
			loadStats();
			loadConversationList();
			loadCannedSidebar();
		} else if (section === 'tickets') {
			loadTicketsList();
		} else if (section === 'knowledge') {
			loadKnowledgeList();
		} else if (section === 'faqs') {
			loadFaqsList();
			loadCannedList();
		} else if (section === 'settings') {
			loadSettings();
		}
	}

	/* =========================================================================
	 * بخش چت زنده - آمار و فیلتر
	 * ========================================================================= */

	/**
	 * بارگذاری آمار لحظه‌ای چت‌ها (بی‌پاسخ، پاسخ‌داده‌شده، بسته‌شده).
	 *
	 * @return {void}
	 */
	function loadStats() {
		ssAdminApi('/admin/stats', 'GET').then(function (data) {
			document.getElementById('ss-stat-unanswered').textContent = data.stats.unanswered;
			document.getElementById('ss-stat-answered').textContent = data.stats.answered;
			document.getElementById('ss-stat-closed').textContent = data.stats.closed;

			var badge = document.getElementById('ss-nav-unread-badge');
			if (data.stats.unanswered > 0) {
				badge.style.display = 'flex';
				badge.textContent = data.stats.unanswered;
			} else {
				badge.style.display = 'none';
			}
		}).catch(handleApiError);
	}

	function initChatFilters() {
		document.getElementById('ss-filter-unread').addEventListener('click', function () {
			state.unreadFilter = true;
			document.getElementById('ss-filter-unread').classList.add('active');
			document.getElementById('ss-filter-all').classList.remove('active');
			loadConversationList();
		});

		document.getElementById('ss-filter-all').addEventListener('click', function () {
			state.unreadFilter = false;
			document.getElementById('ss-filter-all').classList.add('active');
			document.getElementById('ss-filter-unread').classList.remove('active');
			loadConversationList();
		});
	}

	/* =========================================================================
	 * بخش چت زنده - ستون راست (لیست مکالمات)
	 * ========================================================================= */

	var avatarColors = [
		'linear-gradient(135deg, #6366f1, #8b5cf6)',
		'linear-gradient(135deg, #f59e0b, #ef4444)',
		'linear-gradient(135deg, #22c55e, #059669)',
		'linear-gradient(135deg, #ec4899, #be185d)',
		'linear-gradient(135deg, #0ea5e9, #6366f1)'
	];

	/**
	 * بارگذاری لیست مکالمات از سرور و رندر آن در ستون راست.
	 *
	 * @return {void}
	 */
	function loadConversationList() {
		var endpoint = '/admin/conversations' + (state.unreadFilter ? '?unread_only=1' : '');

		ssAdminApi(endpoint, 'GET').then(function (data) {
			renderConversationList(data.conversations);
		}).catch(handleApiError);
	}

	/**
	 * رندر لیست مکالمات در DOM.
	 *
	 * @param {Array} conversations آرایه مکالمات دریافتی از سرور.
	 * @return {void}
	 */
	function renderConversationList(conversations) {
		var container = document.getElementById('ss-chat-list');
		container.innerHTML = '';

		if (!conversations || conversations.length === 0) {
			container.innerHTML = '<div class="ss-empty-table-row">مکالمه‌ای یافت نشد.</div>';
			return;
		}

		conversations.forEach(function (conv, idx) {
			var item = document.createElement('div');
			item.className = 'ss-chat-item' + (state.selectedConversationId === conv.id ? ' selected' : '');
			item.setAttribute('data-id', conv.id);

			var avatarBg = avatarColors[idx % avatarColors.length];
			var timeLabel = formatRelativeTime(conv.last_message_at);

			item.innerHTML =
				'<div class="ss-avatar-wrap">' +
					'<div class="ss-avatar" style="background:' + avatarBg + ';">' + escapeHtml(conv.initial) + '</div>' +
				'</div>' +
				'<div class="ss-chat-item-info">' +
					'<div class="ss-chat-item-top">' +
						'<span class="ss-chat-item-name">' + escapeHtml(conv.name) + '</span>' +
						'<span class="ss-chat-item-time">' + escapeHtml(timeLabel) + '</span>' +
					'</div>' +
					'<div class="ss-chat-item-last">' + escapeHtml(conv.last_message || 'پیامی ثبت نشده') + '</div>' +
				'</div>' +
				(conv.unread_count > 0 ? '<div class="ss-unread-badge">' + conv.unread_count + '</div>' : '');

			item.addEventListener('click', function () {
				selectConversation(conv.id, conv.name, avatarBg);
			});

			container.appendChild(item);
		});
	}

	/**
	 * تبدیل رشته تاریخ MySQL به برچسب زمانی ساده جهت نمایش (فقط ساعت:دقیقه).
	 *
	 * @param {string} mysqlDatetime رشته تاریخ.
	 * @return {string}
	 */
	function formatRelativeTime(mysqlDatetime) {
		if (!mysqlDatetime) {
			return '';
		}
		var parts = mysqlDatetime.split(' ');
		if (parts.length < 2) {
			return mysqlDatetime;
		}
		return parts[1].substring(0, 5);
	}

	/* =========================================================================
	 * بخش چت زنده - ستون وسط (پنجره گفتگو)
	 * ========================================================================= */

	/**
	 * انتخاب یک مکالمه و بارگذاری پنجره گفتگوی آن.
	 *
	 * @param {number} conversationId شناسه مکالمه.
	 * @param {string} name نام نمایشی کاربر.
	 * @param {string} avatarBg گرادیان رنگی آواتار.
	 * @return {void}
	 */
	function selectConversation(conversationId, name, avatarBg) {
		state.selectedConversationId = conversationId;
		state.lastAdminMessageId = 0;

		document.querySelectorAll('.ss-chat-item').forEach(function (el) {
			el.classList.toggle('selected', parseInt(el.getAttribute('data-id'), 10) === conversationId);
		});

		renderChatWindow(name, avatarBg);
		loadConversationMessages(true);
		startConversationPolling();

		// بروزرسانی آمار و لیست پس از باز شدن (چون پیام‌ها خوانده‌شده علامت می‌خورند)
		setTimeout(function () {
			loadStats();
			loadConversationList();
		}, 500);
	}

	/**
	 * رندر اسکلت پنجره گفتگو (هدر + ناحیه پیام + ورودی ارسال) برای مکالمه انتخاب‌شده.
	 *
	 * @param {string} name نام کاربر.
	 * @param {string} avatarBg گرادیان آواتار.
	 * @return {void}
	 */
	function renderChatWindow(name, avatarBg) {
		var col = document.getElementById('ss-chat-window-col');
		var initial = name ? name.charAt(0) : '؟';

		col.innerHTML =
			'<div class="ss-chat-header">' +
				'<div class="ss-chat-header-avatar" style="background:' + avatarBg + ';">' + escapeHtml(initial) + '</div>' +
				'<div class="ss-chat-header-info">' +
					'<div class="ss-chat-header-name">' + escapeHtml(name) + '</div>' +
					'<div class="ss-chat-header-status"><div class="ss-online-dot"></div>آنلاین</div>' +
				'</div>' +
				'<button type="button" class="ss-btn-add-knowledge" id="ss-chat-add-knowledge-btn">' +
					'<svg width="14" height="14" viewBox="0 0 24 24" fill="#22c55e"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path></svg>Add' +
				'</button>' +
			'</div>' +
			'<div class="ss-messages-area" id="ss-chat-messages-area"></div>' +
			'<div class="ss-chat-input-bar">' +
				'<button type="button" class="ss-attach-btn" title="پیوست فایل (به‌زودی)">' +
					'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"></path></svg>' +
				'</button>' +
				'<textarea class="ss-text-input" id="ss-chat-reply-input" placeholder="پیام خود را بنویسید..." rows="1"></textarea>' +
				'<button type="button" class="ss-send-btn" id="ss-chat-reply-send">' +
					'<svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg>' +
				'</button>' +
			'</div>';

		document.getElementById('ss-chat-reply-send').addEventListener('click', sendAdminReply);
		document.getElementById('ss-chat-reply-input').addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				sendAdminReply();
			}
		});
		document.getElementById('ss-chat-add-knowledge-btn').addEventListener('click', addConversationToKnowledge);
	}

	/**
	 * بارگذاری پیام‌های مکالمه انتخاب‌شده و رندر آن‌ها.
	 *
	 * @param {boolean} fullReload بارگذاری کامل (پاک‌کردن قبلی) یا فقط پیام‌های جدید.
	 * @return {void}
	 */
	function loadConversationMessages(fullReload) {
		if (!state.selectedConversationId) {
			return;
		}

		var afterId = fullReload ? 0 : state.lastAdminMessageId;

		ssAdminApi('/admin/conversations/' + state.selectedConversationId + '/messages?after_id=' + afterId, 'GET')
			.then(function (data) {
				if (fullReload) {
					var area = document.getElementById('ss-chat-messages-area');
					if (area) {
						area.innerHTML = '';
					}
				}
				renderMessages(data.messages, 'ss-chat-messages-area');
			})
			.catch(handleApiError);
	}

	/**
	 * رندر یک آرایه پیام در یک ناحیه پیام مشخص (چت زنده یا تیکت).
	 *
	 * @param {Array} messages آرایه پیام‌ها.
	 * @param {string} areaId شناسه المان ناحیه پیام.
	 * @return {void}
	 */
	function renderMessages(messages, areaId) {
		var area = document.getElementById(areaId);
		if (!area || !messages || messages.length === 0) {
			return;
		}

		messages.forEach(function (msg) {
			var isUser = msg.sender_type === 'user';
			var isSystem = msg.sender_type === 'system';
			var rowClass = isUser || isSystem ? 'user' : 'admin';

			var row = document.createElement('div');
			row.className = 'ss-msg-row ' + rowClass;

			var timeLabel = formatRelativeTime(msg.created_at);

			row.innerHTML =
				'<div class="ss-msg-bubble">' +
					escapeHtml(msg.message) +
					'<div class="ss-msg-time">' + escapeHtml(timeLabel) + '</div>' +
				'</div>';

			area.appendChild(row);

			if (msg.id) {
				state.lastAdminMessageId = Math.max(state.lastAdminMessageId, msg.id);
			}
		});

		area.scrollTop = area.scrollHeight;
	}

	/**
	 * ارسال پاسخ ادمین در مکالمه انتخاب‌شده.
	 *
	 * @return {void}
	 */
	function sendAdminReply() {
		var input = document.getElementById('ss-chat-reply-input');
		var text = input.value.trim();

		if (!text || !state.selectedConversationId) {
			return;
		}

		input.value = '';
		input.disabled = true;

		ssAdminApi('/admin/conversations/' + state.selectedConversationId + '/reply', 'POST', { message: text })
			.then(function () {
				input.disabled = false;
				loadConversationMessages(false);
				loadStats();
				loadConversationList();
			})
			.catch(function (error) {
				input.disabled = false;
				input.value = text;
				handleApiError(error);
			});
	}

	/**
	 * خلاصه‌سازی و افزودن مکالمه فعلی به نالج گراف.
	 *
	 * @return {void}
	 */
	function addConversationToKnowledge() {
		if (!state.selectedConversationId) {
			return;
		}

		var btn = document.getElementById('ss-chat-add-knowledge-btn');
		btn.disabled = true;

		ssAdminApi('/admin/conversations/' + state.selectedConversationId + '/add-to-knowledge', 'POST', {})
			.then(function () {
				btn.disabled = false;
				showToast('گفتگو با موفقیت به نالج گراف افزوده شد.', 'success');
			})
			.catch(function (error) {
				btn.disabled = false;
				handleApiError(error);
			});
	}

	/* =========================================================================
	 * چرخه Polling چت زنده (شبیه‌سازی Real-Time بدون WebSocket)
	 * ========================================================================= */

	function startConversationPolling() {
		stopConversationPolling();

		state.conversationPollTimer = setInterval(function () {
			if (state.activeSection === 'liveChat' && state.selectedConversationId) {
				loadConversationMessages(false);
			}
		}, 4000);
	}

	function stopConversationPolling() {
		if (state.conversationPollTimer) {
			clearInterval(state.conversationPollTimer);
			state.conversationPollTimer = null;
		}
	}

	/* =========================================================================
	 * بخش چت زنده - ستون چپ (پیام‌های آماده)
	 * ========================================================================= */

	/**
	 * بارگذاری پیام‌های آماده در سایدبار چپ چت زنده.
	 *
	 * @return {void}
	 */
	function loadCannedSidebar() {
		ssAdminApi('/admin/canned', 'GET').then(function (data) {
			state.cannedResponses = data.canned;
			renderCannedSidebar(data.canned);
		}).catch(handleApiError);
	}

	/**
	 * رندر لیست پیام‌های آماده در سایدبار چپ.
	 *
	 * @param {Array} items آرایه پیام‌های آماده.
	 * @return {void}
	 */
	function renderCannedSidebar(items) {
		var container = document.getElementById('ss-canned-sidebar-list');
		container.innerHTML = '';

		if (!items || items.length === 0) {
			container.innerHTML = '<div class="ss-empty-table-row">پیام آماده‌ای ثبت نشده است.</div>';
			return;
		}

		items.forEach(function (item) {
			var el = document.createElement('div');
			el.className = 'ss-canned-item';
			el.innerHTML = '<div class="ss-canned-item-title">' + escapeHtml(item.title) + '</div>' + escapeHtml(item.text);

			el.addEventListener('click', function () {
				var input = document.getElementById('ss-chat-reply-input');
				if (input) {
					input.value = item.text;
					input.focus();
				} else {
					showToast('ابتدا یک گفتگو را انتخاب کنید.', 'error');
				}
			});

			container.appendChild(el);
		});
	}

	/* =========================================================================
	 * بخش مدیریت تیکت‌ها - لیست و فیلتر
	 * ========================================================================= */

	var statusStyleMap = {
		open:          { bg: 'rgba(59, 130, 246, 0.15)',  color: '#3b82f6' },
		pending:       { bg: 'rgba(234, 179, 8, 0.15)',   color: '#eab308' },
		user_replied:  { bg: 'rgba(236, 72, 153, 0.18)',  color: '#ec4899' },
		resolved:      { bg: 'rgba(34, 197, 94, 0.15)',   color: '#22c55e' },
		closed:        { bg: 'rgba(107, 114, 128, 0.2)',  color: '#9ca3af' }
	};

	var ticketSearchDebounce = null;

	/**
	 * بارگذاری لیست واحدهای تیکت جهت پر کردن دراپ‌داون فیلتر جدول مدیریت تیکت‌ها.
	 *
	 * این لیست مستقل از تب تنظیمات و بلافاصله هنگام لود پنل بارگذاری
	 * می‌شود تا فیلتر همیشه از قبل آماده باشد.
	 *
	 * @return {void}
	 */
	function loadDepartmentsForFilter() {
		ssAdminApi('/departments', 'GET').then(function (data) {
			var select = document.getElementById('ss-ticket-filter-department');
			if (!select) {
				return;
			}

			while (select.options.length > 1) {
				select.remove(1);
			}

			(data.departments || []).forEach(function (dept) {
				var option = document.createElement('option');
				option.value = dept.id;
				option.textContent = dept.name;
				select.appendChild(option);
			});
		}).catch(handleApiError);
	}

	function initTicketFilters() {
		document.getElementById('ss-ticket-filter-department').addEventListener('change', loadTicketsList);
		document.getElementById('ss-ticket-filter-status').addEventListener('change', loadTicketsList);
		document.getElementById('ss-ticket-search').addEventListener('input', function () {
			clearTimeout(ticketSearchDebounce);
			ticketSearchDebounce = setTimeout(loadTicketsList, 400);
		});
		document.getElementById('ss-ticket-back-btn').addEventListener('click', function () {
			document.getElementById('ss-ticket-chat-view').style.display = 'none';
			document.getElementById('ss-tickets-list-view').style.display = 'flex';
			state.selectedTicketId = null;
		});
	}

	/**
	 * بارگذاری لیست تیکت‌ها با فیلترهای فعلی از سرور.
	 *
	 * @return {void}
	 */
	function loadTicketsList() {
		var department = document.getElementById('ss-ticket-filter-department').value;
		var status = document.getElementById('ss-ticket-filter-status').value;
		var search = document.getElementById('ss-ticket-search').value.trim();

		var query = '?department=' + encodeURIComponent(department) +
			'&status=' + encodeURIComponent(status) +
			'&search=' + encodeURIComponent(search);

		ssAdminApi('/admin/tickets' + query, 'GET').then(function (data) {
			renderTicketsTable(data.tickets);
		}).catch(handleApiError);
	}

	/**
	 * رندر جدول تیکت‌ها.
	 *
	 * @param {Array} tickets آرایه تیکت‌های دریافتی.
	 * @return {void}
	 */
	function renderTicketsTable(tickets) {
		var body = document.getElementById('ss-tickets-table-body');
		body.innerHTML = '';

		if (!tickets || tickets.length === 0) {
			body.innerHTML = '<div class="ss-empty-table-row">تیکتی یافت نشد.</div>';
			return;
		}

		tickets.forEach(function (ticket) {
			var style = statusStyleMap[ticket.status_key] || statusStyleMap.open;

			var row = document.createElement('div');
			row.className = 'ss-table-row ss-tickets-grid clickable';
			row.innerHTML =
				'<div class="ss-code-cell">' + escapeHtml(ticket.code) + '</div>' +
				'<div>' + escapeHtml(ticket.name) + '</div>' +
				'<div>' + escapeHtml(ticket.title) + '</div>' +
				'<div>' + escapeHtml(ticket.unit) + '</div>' +
				'<div><span class="ss-status-tag" style="background:' + style.bg + ';color:' + style.color + ';">' + escapeHtml(ticket.status) + '</span></div>' +
				'<div class="ss-date-cell">' + escapeHtml(formatDateOnly(ticket.date)) + '</div>';

			row.addEventListener('click', function () {
				openTicketDetail(ticket.id);
			});

			body.appendChild(row);
		});
	}

	/**
	 * استخراج فقط بخش تاریخ (بدون ساعت) از یک رشته datetime.
	 *
	 * @param {string} datetime رشته کامل تاریخ و زمان.
	 * @return {string}
	 */
	function formatDateOnly(datetime) {
		if (!datetime) {
			return '';
		}
		return datetime.split(' ')[0];
	}

	/* =========================================================================
	 * بخش مدیریت تیکت‌ها - نمای گفتگو
	 * ========================================================================= */

	/**
	 * باز کردن نمای گفتگوی یک تیکت خاص.
	 *
	 * @param {number} ticketId شناسه تیکت.
	 * @return {void}
	 */
	function openTicketDetail(ticketId) {
		state.selectedTicketId = ticketId;

		ssAdminApi('/admin/tickets/' + ticketId, 'GET').then(function (data) {
			document.getElementById('ss-tickets-list-view').style.display = 'none';
			document.getElementById('ss-ticket-chat-view').style.display = 'flex';

			var style = statusStyleMap[data.ticket.status_key] || statusStyleMap.open;

			document.getElementById('ss-ticket-chat-title').textContent = data.ticket.title;
			document.getElementById('ss-ticket-chat-code').textContent = data.ticket.code;

			var statusEl = document.getElementById('ss-ticket-chat-status');
			statusEl.textContent = data.ticket.status;
			statusEl.style.background = style.bg;
			statusEl.style.color = style.color;

			var messagesArea = document.getElementById('ss-ticket-messages-area');
			messagesArea.innerHTML = '';
			renderMessages(data.messages, 'ss-ticket-messages-area');
		}).catch(handleApiError);
	}

	function initTicketChatActions() {
		document.getElementById('ss-ticket-reply-send').addEventListener('click', sendTicketReply);
		document.getElementById('ss-ticket-reply-input').addEventListener('keydown', function (e) {
			if (e.key === 'Enter' && !e.shiftKey) {
				e.preventDefault();
				sendTicketReply();
			}
		});
		document.getElementById('ss-ticket-add-knowledge-btn').addEventListener('click', addTicketToKnowledge);
	}

	/**
	 * ارسال پاسخ ادمین در تیکت انتخاب‌شده.
	 *
	 * @return {void}
	 */
	function sendTicketReply() {
		var input = document.getElementById('ss-ticket-reply-input');
		var text = input.value.trim();

		if (!text || !state.selectedTicketId) {
			return;
		}

		input.value = '';
		input.disabled = true;

		ssAdminApi('/admin/tickets/' + state.selectedTicketId + '/reply', 'POST', { message: text })
			.then(function () {
				input.disabled = false;
				openTicketDetail(state.selectedTicketId);
			})
			.catch(function (error) {
				input.disabled = false;
				input.value = text;
				handleApiError(error);
			});
	}

	/**
	 * خلاصه‌سازی و افزودن تاریخچه تیکت انتخاب‌شده به نالج گراف.
	 *
	 * @return {void}
	 */
	function addTicketToKnowledge() {
		if (!state.selectedTicketId) {
			return;
		}

		var btn = document.getElementById('ss-ticket-add-knowledge-btn');
		btn.disabled = true;

		ssAdminApi('/admin/tickets/' + state.selectedTicketId + '/add-to-knowledge', 'POST', {})
			.then(function () {
				btn.disabled = false;
				showToast('تیکت با موفقیت به نالج گراف افزوده شد.', 'success');
			})
			.catch(function (error) {
				btn.disabled = false;
				handleApiError(error);
			});
	}


	/* =========================================================================
	 * سیستم مودال عمومی (افزودن/ویرایش نالج گراف، FAQ، پیام آماده)
	 * ========================================================================= */

	/**
	 * باز کردن مودال عمومی با عنوان و محتوای HTML مشخص، به همراه تابع ذخیره اختصاصی.
	 *
	 * @param {string} title عنوان مودال.
	 * @param {string} bodyHtml محتوای HTML فرم داخل مودال.
	 * @param {Function} onSave تابعی که هنگام کلیک دکمه ذخیره اجرا می‌شود.
	 * @return {void}
	 */
	function openModal(title, bodyHtml, onSave) {
		document.getElementById('ss-modal-title').textContent = title;
		document.getElementById('ss-modal-body').innerHTML = bodyHtml;
		document.getElementById('ss-modal-overlay').classList.add('open');
		state.modalSaveHandler = onSave;
	}

	/**
	 * بستن مودال عمومی و پاک‌سازی وضعیت آن.
	 *
	 * @return {void}
	 */
	function closeModal() {
		document.getElementById('ss-modal-overlay').classList.remove('open');
		document.getElementById('ss-modal-body').innerHTML = '';
		state.modalSaveHandler = null;
	}

	/**
	 * راه‌اندازی دکمه‌های ثابت مودال (بستن، انصراف، ذخیره).
	 *
	 * @return {void}
	 */
	function initModal() {
		document.getElementById('ss-modal-close').addEventListener('click', closeModal);
		document.getElementById('ss-modal-cancel').addEventListener('click', closeModal);

		document.getElementById('ss-modal-overlay').addEventListener('click', function (e) {
			if (e.target === this) {
				closeModal();
			}
		});

		document.getElementById('ss-modal-save').addEventListener('click', function () {
			if (typeof state.modalSaveHandler === 'function') {
				state.modalSaveHandler();
			}
		});
	}

	/* =========================================================================
	 * بخش نالج گراف - لیست و CRUD
	 * ========================================================================= */

	var knowledgeCache = [];

	/**
	 * بارگذاری لیست کامل گره‌های دانش از سرور و رندر آن در جدول.
	 *
	 * @return {void}
	 */
	function loadKnowledgeList() {
		ssAdminApi('/admin/knowledge', 'GET').then(function (data) {
			knowledgeCache = data.knowledge;
			renderKnowledgeTable(data.knowledge);
		}).catch(handleApiError);
	}

	/**
	 * رندر جدول گره‌های دانش.
	 *
	 * @param {Array} items آرایه گره‌های دانش.
	 * @return {void}
	 */
	function renderKnowledgeTable(items) {
		var body = document.getElementById('ss-knowledge-table-body');
		body.innerHTML = '';

		if (!items || items.length === 0) {
			body.innerHTML = '<div class="ss-empty-table-row">گره دانشی ثبت نشده است.</div>';
			return;
		}

		items.forEach(function (item) {
			var row = document.createElement('div');
			row.className = 'ss-table-row ss-knowledge-grid';

			var tagsHtml = (item.tags || []).map(function (tag) {
				return '<span class="ss-tag-chip">' + escapeHtml(tag) + '</span>';
			}).join('');

			row.innerHTML =
				'<div class="ss-cell-title">' + escapeHtml(item.title) + '</div>' +
				'<div class="ss-truncate">' + escapeHtml(item.content) + '</div>' +
				'<div class="ss-tags-cell">' + tagsHtml + '</div>';

			row.addEventListener('click', function () {
				openKnowledgeModal(item);
			});

			body.appendChild(row);
		});
	}

	/**
	 * باز کردن مودال افزودن یا ویرایش گره دانش.
	 *
	 * @param {Object|null} item گره موجود جهت ویرایش، یا null برای افزودن جدید.
	 * @return {void}
	 */
	function openKnowledgeModal(item) {
		var title = item ? 'ویرایش گره دانش' : 'افزودن گره دانش جدید';
		var titleVal = item ? item.title : '';
		var contentVal = item ? item.content : '';
		var tagsVal = item ? (item.tags || []).join('، ') : '';

		var bodyHtml =
			'<div class="ss-form-group">' +
				'<label style="font-size:12px;font-weight:700;color:var(--ss-text-muted);">عنوان/موضوع</label>' +
				'<input type="text" class="ss-form-input" id="ss-modal-kn-title" value="' + escapeHtml(titleVal) + '">' +
			'</div>' +
			'<div class="ss-form-group">' +
				'<label style="font-size:12px;font-weight:700;color:var(--ss-text-muted);">متن دانش پردازش‌شده</label>' +
				'<textarea class="ss-form-textarea" id="ss-modal-kn-content" style="min-height:100px;">' + escapeHtml(contentVal) + '</textarea>' +
			'</div>' +
			'<div class="ss-form-group">' +
				'<label style="font-size:12px;font-weight:700;color:var(--ss-text-muted);">برچسب‌ها (با کاما جدا کنید)</label>' +
				'<input type="text" class="ss-form-input" id="ss-modal-kn-tags" value="' + escapeHtml(tagsVal) + '">' +
			'</div>' +
			(item ? '<div class="ss-modal-delete-link" id="ss-modal-kn-delete">حذف این گره دانش</div>' : '');

		openModal(title, bodyHtml, function () {
			saveKnowledgeFromModal(item ? item.id : null);
		});

		if (item) {
			document.getElementById('ss-modal-kn-delete').addEventListener('click', function () {
				if (!window.confirm('آیا از حذف این گره دانش مطمئن هستید؟')) {
					return;
				}
				ssAdminApi('/admin/knowledge/' + item.id, 'DELETE').then(function () {
					closeModal();
					showToast('گره دانش حذف شد.', 'success');
					loadKnowledgeList();
				}).catch(handleApiError);
			});
		}
	}

	/**
	 * ذخیره گره دانش از داده‌های فرم مودال (ثبت جدید یا ویرایش).
	 *
	 * @param {number|null} id شناسه گره جهت ویرایش، یا null برای ثبت جدید.
	 * @return {void}
	 */
	function saveKnowledgeFromModal(id) {
		var title = document.getElementById('ss-modal-kn-title').value.trim();
		var content = document.getElementById('ss-modal-kn-content').value.trim();
		var tags = document.getElementById('ss-modal-kn-tags').value.trim();

		if (!title || !content) {
			showToast('لطفاً عنوان و متن دانش را کامل وارد کنید.', 'error');
			return;
		}

		var payload = { title: title, content: content, tags: tags };
		var endpoint = id ? '/admin/knowledge/' + id : '/admin/knowledge';
		var method = id ? 'PUT' : 'POST';

		ssAdminApi(endpoint, method, payload).then(function () {
			closeModal();
			showToast('گره دانش با موفقیت ذخیره شد.', 'success');
			loadKnowledgeList();
		}).catch(handleApiError);
	}

	function initKnowledgeSection() {
		document.getElementById('ss-add-knowledge-btn').addEventListener('click', function () {
			openKnowledgeModal(null);
		});
	}

	/* =========================================================================
	 * بخش سوالات متداول (FAQ) - لیست و CRUD
	 * ========================================================================= */

	/**
	 * بارگذاری لیست سوالات متداول از سرور و رندر آن در جدول.
	 *
	 * @return {void}
	 */
	function loadFaqsList() {
		ssAdminApi('/admin/faqs', 'GET').then(function (data) {
			renderFaqsTable(data.faqs);
		}).catch(handleApiError);
	}

	/**
	 * رندر جدول سوالات متداول.
	 *
	 * @param {Array} items آرایه سوالات متداول.
	 * @return {void}
	 */
	function renderFaqsTable(items) {
		var body = document.getElementById('ss-faq-table-body');
		body.innerHTML = '';

		if (!items || items.length === 0) {
			body.innerHTML = '<div class="ss-empty-table-row">سوال متداولی ثبت نشده است.</div>';
			return;
		}

		items.forEach(function (item) {
			var row = document.createElement('div');
			row.className = 'ss-table-row ss-faq-grid';

			var statusClass = item.status_key === 'active' ? 'active' : 'inactive';

			row.innerHTML =
				'<div class="ss-cell-title">' + escapeHtml(item.question) + '</div>' +
				'<div><span class="ss-status-tag ' + statusClass + '">' + escapeHtml(item.status) + '</span></div>' +
				'<div class="ss-row-actions">' +
					'<button type="button" class="ss-icon-btn edit" title="ویرایش">' +
						'<svg width="14" height="14" viewBox="0 0 24 24" fill="#6366f1"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"></path></svg>' +
					'</button>' +
					'<button type="button" class="ss-icon-btn delete" title="حذف">' +
						'<svg width="14" height="14" viewBox="0 0 24 24" fill="#ef4444"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"></path></svg>' +
					'</button>' +
				'</div>';

			row.querySelector('.edit').addEventListener('click', function () {
				openFaqModal(item);
			});
			row.querySelector('.delete').addEventListener('click', function () {
				if (!window.confirm('آیا از حذف این سوال متداول مطمئن هستید؟')) {
					return;
				}
				ssAdminApi('/admin/faqs/' + item.id, 'DELETE').then(function () {
					showToast('سوال متداول حذف شد.', 'success');
					loadFaqsList();
				}).catch(handleApiError);
			});

			body.appendChild(row);
		});
	}

	/**
	 * باز کردن مودال افزودن یا ویرایش سوال متداول.
	 *
	 * @param {Object|null} item سوال موجود جهت ویرایش، یا null برای افزودن جدید.
	 * @return {void}
	 */
	function openFaqModal(item) {
		var title = item ? 'ویرایش سوال متداول' : 'افزودن سوال جدید';
		var questionVal = item ? item.question : '';
		var answerVal = item ? item.answer : '';
		var statusVal = item ? item.status_key : 'active';

		var bodyHtml =
			'<div class="ss-form-group">' +
				'<label style="font-size:12px;font-weight:700;color:var(--ss-text-muted);">سوال</label>' +
				'<input type="text" class="ss-form-input" id="ss-modal-faq-question" value="' + escapeHtml(questionVal) + '">' +
			'</div>' +
			'<div class="ss-form-group">' +
				'<label style="font-size:12px;font-weight:700;color:var(--ss-text-muted);">پاسخ</label>' +
				'<textarea class="ss-form-textarea" id="ss-modal-faq-answer" style="min-height:80px;">' + escapeHtml(answerVal) + '</textarea>' +
			'</div>' +
			'<div class="ss-form-group">' +
				'<label style="font-size:12px;font-weight:700;color:var(--ss-text-muted);">وضعیت</label>' +
				'<select class="ss-form-input" id="ss-modal-faq-status">' +
					'<option value="active"' + (statusVal === 'active' ? ' selected' : '') + '>فعال</option>' +
					'<option value="inactive"' + (statusVal === 'inactive' ? ' selected' : '') + '>غیرفعال</option>' +
				'</select>' +
			'</div>';

		openModal(title, bodyHtml, function () {
			saveFaqFromModal(item ? item.id : null);
		});
	}

	/**
	 * ذخیره سوال متداول از داده‌های فرم مودال.
	 *
	 * @param {number|null} id شناسه سوال جهت ویرایش، یا null برای ثبت جدید.
	 * @return {void}
	 */
	function saveFaqFromModal(id) {
		var question = document.getElementById('ss-modal-faq-question').value.trim();
		var answer = document.getElementById('ss-modal-faq-answer').value.trim();
		var status = document.getElementById('ss-modal-faq-status').value;

		if (!question || !answer) {
			showToast('لطفاً سوال و پاسخ را کامل وارد کنید.', 'error');
			return;
		}

		var payload = { question: question, answer: answer, status: status };
		var endpoint = id ? '/admin/faqs/' + id : '/admin/faqs';
		var method = id ? 'PUT' : 'POST';

		ssAdminApi(endpoint, method, payload).then(function () {
			closeModal();
			showToast('سوال متداول با موفقیت ذخیره شد.', 'success');
			loadFaqsList();
		}).catch(handleApiError);
	}

	/* =========================================================================
	 * بخش پیام‌های آماده - لیست و CRUD (جدول مدیریت در تب FAQ)
	 * ========================================================================= */

	/**
	 * بارگذاری لیست پیام‌های آماده از سرور و رندر آن در جدول مدیریت.
	 *
	 * @return {void}
	 */
	function loadCannedList() {
		ssAdminApi('/admin/canned', 'GET').then(function (data) {
			state.cannedResponses = data.canned;
			renderCannedTable(data.canned);
		}).catch(handleApiError);
	}

	/**
	 * رندر جدول مدیریت پیام‌های آماده.
	 *
	 * @param {Array} items آرایه پیام‌های آماده.
	 * @return {void}
	 */
	function renderCannedTable(items) {
		var body = document.getElementById('ss-canned-table-body');
		body.innerHTML = '';

		if (!items || items.length === 0) {
			body.innerHTML = '<div class="ss-empty-table-row">پیام آماده‌ای ثبت نشده است.</div>';
			return;
		}

		items.forEach(function (item) {
			var row = document.createElement('div');
			row.className = 'ss-table-row ss-canned-grid';

			row.innerHTML =
				'<div class="ss-cell-title">' + escapeHtml(item.title) + '</div>' +
				'<div class="ss-truncate">' + escapeHtml(item.text) + '</div>' +
				'<div><span class="ss-category-pill">' + escapeHtml(item.category) + '</span></div>' +
				'<div class="ss-row-actions">' +
					'<button type="button" class="ss-icon-btn edit" title="ویرایش">' +
						'<svg width="14" height="14" viewBox="0 0 24 24" fill="#6366f1"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"></path></svg>' +
					'</button>' +
					'<button type="button" class="ss-icon-btn delete" title="حذف">' +
						'<svg width="14" height="14" viewBox="0 0 24 24" fill="#ef4444"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"></path></svg>' +
					'</button>' +
				'</div>';

			row.querySelector('.edit').addEventListener('click', function () {
				openCannedModal(item);
			});
			row.querySelector('.delete').addEventListener('click', function () {
				if (!window.confirm('آیا از حذف این پیام آماده مطمئن هستید؟')) {
					return;
				}
				ssAdminApi('/admin/canned/' + item.id, 'DELETE').then(function () {
					showToast('پیام آماده حذف شد.', 'success');
					loadCannedList();
				}).catch(handleApiError);
			});

			body.appendChild(row);
		});
	}

	/**
	 * باز کردن مودال افزودن یا ویرایش پیام آماده.
	 *
	 * @param {Object|null} item پیام موجود جهت ویرایش، یا null برای افزودن جدید.
	 * @return {void}
	 */
	function openCannedModal(item) {
		var title = item ? 'ویرایش پیام آماده' : 'افزودن پیام آماده';
		var titleVal = item ? item.title : '';
		var textVal = item ? item.text : '';
		var categoryVal = item ? item.category : '';

		var bodyHtml =
			'<div class="ss-form-group">' +
				'<label style="font-size:12px;font-weight:700;color:var(--ss-text-muted);">عنوان</label>' +
				'<input type="text" class="ss-form-input" id="ss-modal-cn-title" value="' + escapeHtml(titleVal) + '">' +
			'</div>' +
			'<div class="ss-form-group">' +
				'<label style="font-size:12px;font-weight:700;color:var(--ss-text-muted);">متن پیام</label>' +
				'<textarea class="ss-form-textarea" id="ss-modal-cn-text" style="min-height:80px;">' + escapeHtml(textVal) + '</textarea>' +
			'</div>' +
			'<div class="ss-form-group">' +
				'<label style="font-size:12px;font-weight:700;color:var(--ss-text-muted);">دسته</label>' +
				'<input type="text" class="ss-form-input" id="ss-modal-cn-category" value="' + escapeHtml(categoryVal) + '">' +
			'</div>';

		openModal(title, bodyHtml, function () {
			saveCannedFromModal(item ? item.id : null);
		});
	}

	/**
	 * ذخیره پیام آماده از داده‌های فرم مودال.
	 *
	 * @param {number|null} id شناسه پیام جهت ویرایش، یا null برای ثبت جدید.
	 * @return {void}
	 */
	function saveCannedFromModal(id) {
		var title = document.getElementById('ss-modal-cn-title').value.trim();
		var text = document.getElementById('ss-modal-cn-text').value.trim();
		var category = document.getElementById('ss-modal-cn-category').value.trim();

		if (!title || !text) {
			showToast('لطفاً عنوان و متن پیام را کامل وارد کنید.', 'error');
			return;
		}

		var payload = { title: title, message: text, category: category || 'عمومی' };
		var endpoint = id ? '/admin/canned/' + id : '/admin/canned';
		var method = id ? 'PUT' : 'POST';

		ssAdminApi(endpoint, method, payload).then(function () {
			closeModal();
			showToast('پیام آماده با موفقیت ذخیره شد.', 'success');
			loadCannedList();
		}).catch(handleApiError);
	}

	/**
	 * راه‌اندازی تب‌های داخلی بخش FAQ/پیام‌آماده و دکمه‌های افزودن.
	 *
	 * @return {void}
	 */
	function initFaqsSection() {
		document.getElementById('ss-faq-tab-btn').addEventListener('click', function () {
			state.faqSubTab = 'faq';
			document.getElementById('ss-faq-tab-btn').classList.add('active');
			document.getElementById('ss-canned-tab-btn').classList.remove('active');
			document.getElementById('ss-faq-tab-content').style.display = '';
			document.getElementById('ss-canned-tab-content').style.display = 'none';
		});

		document.getElementById('ss-canned-tab-btn').addEventListener('click', function () {
			state.faqSubTab = 'canned';
			document.getElementById('ss-canned-tab-btn').classList.add('active');
			document.getElementById('ss-faq-tab-btn').classList.remove('active');
			document.getElementById('ss-canned-tab-content').style.display = '';
			document.getElementById('ss-faq-tab-content').style.display = 'none';
		});

		document.getElementById('ss-add-faq-btn').addEventListener('click', function () {
			openFaqModal(null);
		});

		document.getElementById('ss-add-canned-btn').addEventListener('click', function () {
			openCannedModal(null);
		});
	}

	/* =========================================================================
	 * بخش تنظیمات پیشرفته - سه تب (ظاهری، هوش مصنوعی، منطق پاسخ‌دهی)
	 * ========================================================================= */

	/**
	 * راه‌اندازی جابجایی بین تب‌های داخلی صفحه تنظیمات.
	 *
	 * @return {void}
	 */
	function initSettingsTabs() {
		var tabButtons = document.querySelectorAll('.ss-settings-tab-btn');

		tabButtons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var tab = btn.getAttribute('data-settingstab');
				state.settingsTab = tab;

				tabButtons.forEach(function (b) {
					b.classList.toggle('active', b === btn);
				});

				document.getElementById('ss-settings-ui-content').style.display = tab === 'ui' ? '' : 'none';
				document.getElementById('ss-settings-ai-content').style.display = tab === 'ai' ? '' : 'none';
				document.getElementById('ss-settings-logic-content').style.display = tab === 'logic' ? '' : 'none';
				document.getElementById('ss-settings-ticket-content').style.display = tab === 'ticket' ? '' : 'none';
			});
		});
	}

	/**
	 * بارگذاری هر سه دسته تنظیمات از سرور و پر کردن فیلدهای فرم.
	 *
	 * @return {void}
	 */
	function loadSettings() {
		ssAdminApi('/admin/settings', 'GET').then(function (data) {
			// تنظیمات ظاهری
			document.getElementById('ss-setting-welcome-text').value = data.ui.welcome_text || '';
			document.getElementById('ss-setting-tooltip-text').value = data.ui.tooltip_text || '';
			document.getElementById('ss-setting-bale-link').value = data.ui.bale_link || '';
			document.getElementById('ss-setting-telegram-link').value = data.ui.telegram_link || '';
			document.getElementById('ss-setting-rubika-link').value = data.ui.rubika_link || '';
			updateMediaPreview('ss-logo-preview', data.ui.logo_url);
			updateSocialIconPreview('ss-bale-icon-preview', data.ui.bale_icon_url);
			updateSocialIconPreview('ss-telegram-icon-preview', data.ui.telegram_icon_url);
			updateSocialIconPreview('ss-rubika-icon-preview', data.ui.rubika_icon_url);
			state.settingsData = { ui: data.ui };

			// واحدهای مربوطه تیکت
			state.departments = data.departments || [];
			renderDepartmentsList();

			// تنظیمات هوش مصنوعی
			document.getElementById('ss-setting-ai-base-url').value = data.ai.base_url || '';
			document.getElementById('ss-setting-ai-api-key').value = data.ai.api_key || '';
			document.getElementById('ss-setting-ai-model').value = data.ai.model || '';

			// تنظیمات منطق پاسخ‌دهی
			document.getElementById('ss-setting-negative-prompt').value = data.logic.negative_prompt || '';
			setResponseMode(data.logic.response_mode || 'human');
		}).catch(handleApiError);
	}

	/* =========================================================================
	 * مدیریت واحدهای مربوطه تیکت (افزودن/ویرایش/حذف/ترتیب)
	 * ========================================================================= */

	/**
	 * رندر لیست ردیف‌های واحد در تب تنظیمات ظاهری بر اساس state.departments.
	 *
	 * @return {void}
	 */
	function renderDepartmentsList() {
		var container = document.getElementById('ss-departments-list');
		if (!container) {
			return;
		}

		container.innerHTML = '';

		(state.departments || []).forEach(function (dept, index) {
			var row = document.createElement('div');
			row.className = 'ss-department-row';

			var input = document.createElement('input');
			input.type = 'text';
			input.className = 'ss-form-input';
			input.value = dept.name || '';
			input.placeholder = 'نام واحد';
			input.addEventListener('input', function () {
				state.departments[index].name = input.value;
			});

			var deleteBtn = document.createElement('button');
			deleteBtn.type = 'button';
			deleteBtn.className = 'ss-department-delete-btn';
			deleteBtn.title = 'حذف این واحد';
			deleteBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="#ef4444"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"></path></svg>';
			deleteBtn.addEventListener('click', function () {
				state.departments.splice(index, 1);
				renderDepartmentsList();
			});

			row.appendChild(input);
			row.appendChild(deleteBtn);
			container.appendChild(row);
		});
	}

	/**
	 * افزودن یک ردیف خالی جدید به لیست واحدها (بدون شناسه؛ شناسه هنگام ذخیره در سرور تولید می‌شود).
	 *
	 * @return {void}
	 */
	function addDepartmentRow() {
		if (!state.departments) {
			state.departments = [];
		}
		state.departments.push({ id: 0, name: '' });
		renderDepartmentsList();

		// فوکوس خودکار روی آخرین اینپوت افزوده‌شده جهت تجربه کاربری بهتر
		var container = document.getElementById('ss-departments-list');
		var lastInput = container.querySelector('.ss-department-row:last-child input');
		if (lastInput) {
			lastInput.focus();
		}
	}

	/**
	 * به‌روزرسانی پیش‌نمایش لوگو در صورت وجود آدرس تصویر ذخیره‌شده.
	 *
	 * @param {string} elementId شناسه المان پیش‌نمایش.
	 * @param {string} url آدرس تصویر.
	 * @return {void}
	 */
	function updateMediaPreview(elementId, url) {
		var el = document.getElementById(elementId);
		if (url) {
			el.style.backgroundImage = 'url(' + url + ')';
			el.style.backgroundSize = 'cover';
			el.style.backgroundPosition = 'center';
			el.innerHTML = '';
		}
	}

	/**
	 * به‌روزرسانی پیش‌نمایش آیکون شبکه اجتماعی در صورت وجود آدرس تصویر سفارشی.
	 *
	 * @param {string} elementId شناسه المان پیش‌نمایش.
	 * @param {string} url آدرس تصویر.
	 * @return {void}
	 */
	function updateSocialIconPreview(elementId, url) {
		if (!url) {
			return;
		}
		var el = document.getElementById(elementId);
		el.style.backgroundImage = 'url(' + url + ')';
		el.style.backgroundSize = 'cover';
		el.style.backgroundPosition = 'center';
		el.innerHTML = '';
	}

	/**
	 * تنظیم حالت انتخاب‌شده گزینه‌های رادیویی وضعیت پاسخ‌دهی.
	 *
	 * @param {string} mode حالت انتخاب‌شده ('human', 'ai', 'hybrid').
	 * @return {void}
	 */
	function setResponseMode(mode) {
		state.responseMode = mode;
		document.querySelectorAll('.ss-radio-item').forEach(function (item) {
			item.classList.toggle('selected', item.getAttribute('data-mode') === mode);
		});
	}

	/**
	 * راه‌اندازی رویدادهای فرم تنظیمات (ذخیره هر تب، انتخاب رادیویی، رسانه وردپرس).
	 *
	 * @return {void}
	 */
	function initSettingsForm() {
		// انتخاب حالت پاسخ‌دهی
		document.querySelectorAll('.ss-radio-item').forEach(function (item) {
			item.addEventListener('click', function () {
				setResponseMode(item.getAttribute('data-mode'));
			});
		});

		// ذخیره تنظیمات ظاهری
		document.getElementById('ss-save-ui-settings').addEventListener('click', function () {
			var payload = {
				logo_url: (state.settingsData && state.settingsData.ui && state.settingsData.ui.logo_url) || '',
				welcome_text: document.getElementById('ss-setting-welcome-text').value.trim(),
				tooltip_text: document.getElementById('ss-setting-tooltip-text').value.trim(),
				bale_link: document.getElementById('ss-setting-bale-link').value.trim(),
				bale_icon_url: (state.settingsData && state.settingsData.ui && state.settingsData.ui.bale_icon_url) || '',
				telegram_link: document.getElementById('ss-setting-telegram-link').value.trim(),
				telegram_icon_url: (state.settingsData && state.settingsData.ui && state.settingsData.ui.telegram_icon_url) || '',
				rubika_link: document.getElementById('ss-setting-rubika-link').value.trim(),
				rubika_icon_url: (state.settingsData && state.settingsData.ui && state.settingsData.ui.rubika_icon_url) || ''
			};

			ssAdminApi('/admin/settings/ui', 'POST', payload).then(function () {
				showToast('تنظیمات ظاهری ذخیره شد.', 'success');
			}).catch(handleApiError);
		});

		// ذخیره لیست واحدهای مربوطه تیکت (تب مستقل تنظیمات تیکت)
		document.getElementById('ss-save-ticket-settings').addEventListener('click', function () {
			// حذف ردیف‌های واحد با نام خالی قبل از ارسال (کاربر ممکن است یک ردیف را افزوده و پر نکرده باشد)
			var departmentsPayload = (state.departments || []).filter(function (dept) {
				return dept.name && dept.name.trim() !== '';
			});

			ssAdminApi('/admin/settings/departments', 'POST', { departments: departmentsPayload })
				.then(function (result) {
					// همگام‌سازی state با شناسه‌های نهایی تولیدشده توسط سرور برای واحدهای تازه اضافه‌شده
					state.departments = result.departments || [];
					renderDepartmentsList();
					showToast('تنظیمات تیکت ذخیره شد.', 'success');
					// به‌روزرسانی فوری فیلتر واحد در جدول مدیریت تیکت‌ها با لیست تازه ذخیره‌شده
					loadDepartmentsForFilter();
				})
				.catch(handleApiError);
		});

		// افزودن ردیف خالی جدید به لیست واحدهای تیکت
		document.getElementById('ss-add-department-btn').addEventListener('click', addDepartmentRow);

		// ذخیره تنظیمات هوش مصنوعی
		document.getElementById('ss-save-ai-settings').addEventListener('click', function () {
			var payload = {
				api_key: document.getElementById('ss-setting-ai-api-key').value.trim(),
				model: document.getElementById('ss-setting-ai-model').value.trim()
			};

			ssAdminApi('/admin/settings/ai', 'POST', payload).then(function () {
				showToast('تنظیمات هوش مصنوعی ذخیره شد.', 'success');
			}).catch(handleApiError);
		});

		// تست زنده اتصال هوش مصنوعی
		document.getElementById('ss-test-ai-connection').addEventListener('click', function () {
			var resultEl = document.getElementById('ss-test-ai-result');
			resultEl.textContent = 'در حال بررسی اتصال...';
			resultEl.className = 'ss-test-connection-result';

			ssAdminApi('/admin/settings/ai/test', 'POST', {}).then(function (data) {
				resultEl.textContent = data.message;
				resultEl.className = 'ss-test-connection-result ' + (data.success ? 'success' : 'error');
			}).catch(function (error) {
				resultEl.textContent = error.message || 'خطا در تست اتصال.';
				resultEl.className = 'ss-test-connection-result error';
			});
		});

		// ذخیره تنظیمات منطق پاسخ‌دهی
		document.getElementById('ss-save-logic-settings').addEventListener('click', function () {
			var payload = {
				response_mode: state.responseMode,
				negative_prompt: document.getElementById('ss-setting-negative-prompt').value.trim()
			};

			ssAdminApi('/admin/settings/logic', 'POST', payload).then(function () {
				showToast('تنظیمات منطق پاسخ‌دهی ذخیره شد.', 'success');
			}).catch(handleApiError);
		});

		// دکمه‌های انتخاب از رسانه وردپرس (لوگو و آیکون‌های شبکه اجتماعی)
		document.querySelectorAll('.ss-media-select-btn, .ss-social-media-btn').forEach(function (btn) {
			btn.addEventListener('click', function () {
				openWordPressMediaPicker(btn.getAttribute('data-media-target'));
			});
		});
	}

	/**
	 * باز کردن رسانه وردپرس (wp.media) جهت انتخاب تصویر لوگو یا آیکون شبکه اجتماعی.
	 *
	 * @param {string} targetKey کلید فیلد تنظیمات که مقدار انتخاب‌شده باید در آن ذخیره شود.
	 * @return {void}
	 */
	function openWordPressMediaPicker(targetKey) {
		if (typeof wp === 'undefined' || !wp.media) {
			showToast('کتابخانه رسانه وردپرس در دسترس نیست.', 'error');
			return;
		}

		var frame = wp.media({
			title: 'انتخاب تصویر',
			button: { text: 'انتخاب این تصویر' },
			multiple: false,
			library: { type: 'image' }
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var url = attachment.url;

			if (!state.settingsData) {
				state.settingsData = { ui: {} };
			}
			if (!state.settingsData.ui) {
				state.settingsData.ui = {};
			}
			state.settingsData.ui[targetKey] = url;

			if (targetKey === 'logo_url') {
				updateMediaPreview('ss-logo-preview', url);
			} else if (targetKey === 'bale_icon_url') {
				updateSocialIconPreview('ss-bale-icon-preview', url);
			} else if (targetKey === 'telegram_icon_url') {
				updateSocialIconPreview('ss-telegram-icon-preview', url);
			} else if (targetKey === 'rubika_icon_url') {
				updateSocialIconPreview('ss-rubika-icon-preview', url);
			}
		});

		frame.open();
	}

	/* =========================================================================
	 * راه‌اندازی نهایی برنامه هنگام بارگذاری کامل صفحه
	 * ========================================================================= */

	document.addEventListener('DOMContentLoaded', function () {
		initTheme();
		initNavigation();
		initChatFilters();
		initTicketFilters();
		initTicketChatActions();
		initModal();
		initKnowledgeSection();
		initFaqsSection();
		initSettingsTabs();
		initSettingsForm();

		// بارگذاری اولیه داده‌های بخش پیش‌فرض (چت زنده)
		loadStats();
		loadConversationList();
		loadCannedSidebar();
		loadDepartmentsForFilter();
	});

})();

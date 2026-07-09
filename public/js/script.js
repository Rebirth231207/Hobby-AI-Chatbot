/**
 * اسکریپت اصلی ویجت پشتیبانی هوشمند (سمت کاربر).
 *
 * این فایل نسخه متصل‌شده به REST API واقعی وردپرس از اسکریپت اولیه
 * Front.zip است. تمام رفتارهای ظاهری (باز/بسته شدن، تعویض تب، تعویض
 * تم، انیمیشن‌های تایپینگ و...) دقیقاً حفظ شده‌اند؛ تنها تفاوت این
 * است که به‌جای شبیه‌سازی پاسخ با setTimeout، اکنون پیام‌ها واقعاً
 * از طریق WP REST API ارسال، دریافت و ذخیره می‌شوند.
 *
 * جاوااسکریپت کاملاً خالص (Vanilla JS) بدون هیچ وابستگی به فریمورک.
 */

/* =========================================================================
 * محافظ اسکرول صفحه اصلی سایت
 * ========================================================================= */

/**
 * این تابع تضمین می‌کند که اسکرول صفحه اصلی سایت وردپرس هرگز توسط این
 * افزونه قفل نشود. این افزونه هرگز عمداً overflow صفحه را دست‌کاری
 * نمی‌کند؛ این تابع صرفاً یک محافظ فعال است که هرگونه مقدار inline
 * style روی document.body که ممکن است توسط اسکریپت دیگری به اشتباه
 * تنظیم شده باشد را در صورت وجود پاک می‌کند.
 *
 * @return {void}
 */
function ssEnsurePageScrollable() {
	if (document.body.style.overflow === 'hidden') {
		document.body.style.overflow = '';
	}
	if (document.documentElement.style.overflow === 'hidden') {
		document.documentElement.style.overflow = '';
	}
}

ssEnsurePageScrollable();
document.addEventListener('DOMContentLoaded', ssEnsurePageScrollable);

// وضعیت‌های کلی ابزار چت
var state = {
	isOpen: false,
	activeTab: 'live',
	chatStarted: false,
	isDark: true,
	ticketView: 'menu',
	messages: [],
	replyingTo: null,
	conversationUid: null,
	conversationId: null,
	lastMessageId: 0,
	pollingTimer: null,
	pendingAttachmentUrl: '',
	currentTicketPhone: '',
	currentTicketId: null,
	selectedDepartment: '',
	ticketAttachmentUrl: '',
	faqs: [], // سوالات متداول فعال، به صورت داینامیک از سرور (پنل ادمین) خوانده می‌شود
	departments: [], // واحدهای مربوطه تیکت، به صورت داینامیک از تنظیمات پنل ادمین خوانده می‌شود
	isFaqPending: false // جلوگیری از تداخل پولینگ با پاسخ‌دهی خودکار FAQ در حال پردازش
};

/* =========================================================================
 * بخش کمکی: ارتباط با REST API وردپرس
 * ========================================================================= */

/**
 * ارسال درخواست به REST API افزونه با هدر نانس امنیتی.
 *
 * @param {string} endpoint مسیر نسبی endpoint (مثلاً '/conversation/start').
 * @param {string} method متد HTTP ('GET' یا 'POST').
 * @param {Object|null} bodyData داده‌های ارسالی به صورت شیء (برای POST).
 * @return {Promise<Object>} پرامیس حاوی پاسخ JSON سرور.
 */
function ssApiRequest(endpoint, method, bodyData) {
	var url = SS_Data.rest_url + endpoint;

	var options = {
		method: method,
		headers: {
			'X-WP-Nonce': SS_Data.nonce
		}
	};

	if (method === 'POST' && bodyData) {
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
 * آپلود یک فایل به سرور از طریق FormData (بدون Content-Type دستی جهت boundary صحیح).
 *
 * @param {string} endpoint مسیر نسبی endpoint آپلود.
 * @param {File} file فایل انتخاب‌شده توسط کاربر.
 * @param {string} fieldName نام فیلد فایل در FormData.
 * @param {Object} extraFields سایر فیلدهای متنی جهت افزودن به FormData.
 * @return {Promise<Object>}
 */
function ssUploadRequest(endpoint, file, fieldName, extraFields) {
	var url = SS_Data.rest_url + endpoint;
	var formData = new FormData();
	formData.append(fieldName, file);

	if (extraFields) {
		for (var key in extraFields) {
			if (extraFields.hasOwnProperty(key)) {
				formData.append(key, extraFields[key]);
			}
		}
	}

	return fetch(url, {
		method: 'POST',
		headers: {
			'X-WP-Nonce': SS_Data.nonce
		},
		body: formData
	}).then(function (response) {
		return response.json().then(function (data) {
			if (!response.ok) {
				var errMessage = (data && data.message) ? data.message : 'خطا در آپلود فایل رخ داد.';
				throw new Error(errMessage);
			}
			return data;
		});
	});
}

/* =========================================================================
 * بخش حفظ وضعیت چت (Session Persistence) با sessionStorage
 * ========================================================================= */

/**
 * دریافت یا ساخت شناسه یکتای مکالمه از sessionStorage جهت حفظ وضعیت چت
 * در صورت رفرش صفحه یا جابجایی بین صفحات سایت.
 *
 * @return {string} شناسه یکتای مکالمه.
 */
function getOrCreateConversationUid() {
	var stored = sessionStorage.getItem('ss_conversation_uid');

	if (stored) {
		return stored;
	}

	var newUid = 'ss_' + Date.now() + '_' + Math.random().toString(36).substring(2, 10);
	sessionStorage.setItem('ss_conversation_uid', newUid);
	return newUid;
}

/* =========================================================================
 * بخش سوالات متداول (FAQ) - بارگذاری داینامیک از دیتابیس پنل ادمین
 * ========================================================================= */

/**
 * دریافت لیست سوالات متداول فعال از سرور و رندر آن‌ها به صورت کپسولی.
 *
 * اگر ادمین هیچ سوالی در پنل مدیریت تعریف نکرده باشد (یا همه را
 * غیرفعال کرده باشد)، هیچ کارتی نمایش داده نمی‌شود و فضای مربوطه در
 * صفحه خوش‌آمدگویی خالی می‌ماند.
 *
 * @return {void}
 */
function loadFaqs() {
	ssApiRequest('/faqs', 'GET').then(function (data) {
		state.faqs = data.faqs || [];
		renderFaqGrid();
	}).catch(function (error) {
		console.error('خطا در دریافت سوالات متداول:', error);
	});
}

/**
 * رندر کارت‌های سوالات متداول در صفحه خوش‌آمدگویی بر اساس داده واقعی سرور.
 *
 * @return {void}
 */
function renderFaqGrid() {
	var grid = document.getElementById('faq-grid');
	if (!grid) {
		return;
	}

	grid.innerHTML = '';

	if (!state.faqs || state.faqs.length === 0) {
		// هیچ سوال متداول فعالی تعریف نشده - گرید کاملاً خالی می‌ماند
		grid.style.display = 'none';
		return;
	}

	grid.style.display = '';

	state.faqs.forEach(function (faq) {
		var card = document.createElement('div');
		card.className = 'faq-card';
		card.textContent = faq.question;
		card.addEventListener('click', function () {
			handleFAQ(faq.question);
		});
		grid.appendChild(card);
	});
}

/* =========================================================================
 * بخش واحدهای مربوطه تیکت - بارگذاری داینامیک از تنظیمات پنل ادمین
 * ========================================================================= */

/**
 * دریافت لیست واحدهای تیکت از سرور و پر کردن دراپ‌داون فرم ثبت تیکت.
 *
 * @return {void}
 */
function loadDepartments() {
	ssApiRequest('/departments', 'GET').then(function (data) {
		state.departments = data.departments || [];
		renderDepartmentOptions();
	}).catch(function (error) {
		console.error('خطا در دریافت لیست واحدهای تیکت:', error);
	});
}

/**
 * رندر گزینه‌های دراپ‌داون «واحد مربوطه» بر اساس داده واقعی سرور.
 *
 * @return {void}
 */
function renderDepartmentOptions() {
	var select = document.getElementById('ticket-department');
	if (!select) {
		return;
	}

	// حذف تمام گزینه‌ها به جز گزینه پیش‌فرض «انتخاب کنید» (اولین option)
	while (select.options.length > 1) {
		select.remove(1);
	}

	(state.departments || []).forEach(function (dept) {
		var option = document.createElement('option');
		option.value = dept.id;
		option.textContent = dept.name;
		select.appendChild(option);
	});
}

/* =========================================================================
 * راه‌اندازی اولیه هنگام لود صفحه
 * ========================================================================= */

document.addEventListener('DOMContentLoaded', function () {
	state.conversationUid = getOrCreateConversationUid();
	loadFaqs();
	loadDepartments();
});

/* =========================================================================
 * بخش رابط کاربری عمومی (باز/بسته کردن، تب‌ها، تم) - بدون تغییر نسبت به نسخه اصلی
 * ========================================================================= */

// باز و بسته کردن ابزار پشتیبانی
function toggleWidget() {
	state.isOpen = !state.isOpen;
	var widget = document.getElementById('support-widget');
	var iconChat = document.getElementById('icon-chat');
	var iconClose = document.getElementById('icon-close');
	var tooltip = document.getElementById('fab-tooltip');

	if (state.isOpen) {
		widget.classList.remove('hidden');
		iconChat.classList.add('hidden');
		iconClose.classList.remove('hidden');
		tooltip.classList.add('hidden');

		// اولین بار که ویجت باز می‌شود، مکالمه را با سرور مقداردهی اولیه می‌کنیم
		if (!state.conversationId) {
			initConversation();
		}
	} else {
		widget.classList.add('hidden');
		iconChat.classList.remove('hidden');
		iconClose.classList.add('hidden');
		tooltip.classList.remove('hidden');

		stopPolling();
	}
}

// تغییر تم تاریک و روشن
function toggleTheme() {
	state.isDark = !state.isDark;
	var widget = document.getElementById('support-widget');
	var sunIcon = document.getElementById('theme-sun');
	var moonIcon = document.getElementById('theme-moon');

	if (state.isDark) {
		widget.classList.remove('light');
		widget.classList.add('dark');
		sunIcon.classList.add('hidden');
		moonIcon.classList.remove('hidden');
	} else {
		widget.classList.remove('dark');
		widget.classList.add('light');
		sunIcon.classList.remove('hidden');
		moonIcon.classList.add('hidden');
	}
}

// تغییر تب چت زنده و تیکت‌ها
function switchTab(tab) {
	state.activeTab = tab;
	var tabLive = document.getElementById('tab-live');
	var tabTicket = document.getElementById('tab-ticket');
	var contentLive = document.getElementById('content-live');
	var contentTicket = document.getElementById('content-ticket');

	if (tab === 'live') {
		tabLive.classList.add('active');
		tabTicket.classList.remove('active');
		contentLive.classList.remove('hidden');
		contentTicket.classList.add('hidden');
	} else {
		tabLive.classList.remove('active');
		tabTicket.classList.add('active');
		contentLive.classList.add('hidden');
		contentTicket.classList.remove('hidden');
	}
}

/* =========================================================================
 * بخش چت زنده - اتصال واقعی به REST API وردپرس
 * ========================================================================= */

/**
 * مقداردهی اولیه مکالمه با سرور: بازیابی مکالمه قبلی (در صورت وجود Session)
 * یا ساخت مکالمه جدید. در صورت وجود پیام‌های قبلی، آن‌ها بازسازی می‌شوند.
 *
 * @param {Function} [onReady] تابعی که پس از آماده شدن کامل مکالمه اجرا می‌شود (اختیاری).
 * @return {void}
 */
function initConversation(onReady) {
	ssApiRequest('/conversation/start', 'POST', {
		conversation_uid: state.conversationUid,
		guest_name: SS_Data.is_logged_in ? SS_Data.current_user_name : '',
		guest_phone: ''
	}).then(function (data) {
		state.conversationId = data.conversation_id;

		// اگر مکالمه قبلی با پیام موجود بود، رابط کاربری را با تاریخچه واقعی بازسازی می‌کنیم
		if (!data.is_new && data.messages && data.messages.length > 0) {
			restoreConversation(data.messages);
		}

		startPolling();

		if (typeof onReady === 'function') {
			onReady();
		}
	}).catch(function (error) {
		console.error('خطا در راه‌اندازی مکالمه:', error);
	});
}

/**
 * بازسازی کامل تاریخچه چت در رابط کاربری هنگام بازگشت کاربر (رفرش صفحه).
 *
 * @param {Array} messages آرایه پیام‌های دریافتی از سرور.
 * @return {void}
 */
function restoreConversation(messages) {
	state.chatStarted = true;
	document.getElementById('live-welcome').classList.add('hidden');
	document.getElementById('live-chat').classList.remove('hidden');

	messages.forEach(function (msg) {
		if (msg.sender_type === 'user') {
			renderUserMessage(msg.message, msg.reply_to_text);
		} else {
			renderBotMessage(msg.message);
		}
		state.lastMessageId = msg.id;
	});
}

/**
 * شروع چرخه Polling برای دریافت پیام‌های جدید از سمت اپراتور/هوش مصنوعی.
 *
 * از آنجا که در PHP 7.4 و بدون فریمورک نمی‌توان از WebSocket بومی
 * استفاده کرد، این مکانیزم با فاصله زمانی کوتاه، سرور را برای پیام‌های
 * جدید بررسی می‌کند تا حس Real-Time را برای کاربر فراهم کند.
 *
 * @return {void}
 */
function startPolling() {
	stopPolling();

	state.pollingTimer = setInterval(function () {
		if (!state.conversationId) {
			return;
		}

		// در حین پردازش پاسخ خودکار FAQ، پولینگ موقتاً متوقف می‌شود تا از
		// نمایش تکراری همان پیام (یک‌بار توسط FAQ و یک‌بار توسط پولینگ) جلوگیری شود
		if (state.isFaqPending) {
			return;
		}

		ssApiRequest('/conversation/' + state.conversationUid + '/messages?after_id=' + state.lastMessageId, 'GET')
			.then(function (data) {
				if (data.messages && data.messages.length > 0) {
					data.messages.forEach(function (msg) {
						if (msg.sender_type !== 'user') {
							hideTypingIndicator();
							renderBotMessage(msg.message);
						}
						state.lastMessageId = Math.max(state.lastMessageId, msg.id);
					});
				}
			})
			.catch(function (error) {
				console.error('خطا در دریافت پیام‌های جدید:', error);
			});
	}, 4000);
}

/**
 * توقف چرخه Polling (هنگام بسته شدن ویجت جهت صرفه‌جویی در منابع).
 *
 * @return {void}
 */
function stopPolling() {
	if (state.pollingTimer) {
		clearInterval(state.pollingTimer);
		state.pollingTimer = null;
	}
}

// شروع چت زنده
function startLiveChat() {
	state.chatStarted = true;
	document.getElementById('live-welcome').classList.add('hidden');
	document.getElementById('live-chat').classList.remove('hidden');

	// نمایش پیام خوش‌آمدگویی فقط در صورتی که مکالمه هنوز هیچ پیامی نداشته باشد
	if (state.messages.length === 0 && document.getElementById('chat-messages').children.length === 0) {
		renderBotMessage('سلام! خوش آمدید. چطور می‌توانم امروز به شما کمک کنم؟');
	}
}

// بازگشت به صفحه خوش‌آمدگویی چت زنده
function backToWelcome() {
	state.chatStarted = false;
	document.getElementById('live-welcome').classList.remove('hidden');
	document.getElementById('live-chat').classList.add('hidden');
}

// بررسی کلید اینتر در باکس پیام چت
function checkEnter(event) {
	if (event.key === 'Enter') {
		sendMessage();
	}
}

/**
 * ارسال پیام کاربر به سرور از طریق REST API واقعی.
 *
 * @return {void}
 */
function sendMessage() {
	var input = document.getElementById('message-input');
	var text = input.value.trim();
	if (!text) {
		return;
	}

	var replyTo = state.replyingTo;

	// نمایش فوری پیام کاربر در رابط کاربری (خوش‌بینانه - Optimistic UI)
	renderUserMessage(text, replyTo);
	input.value = '';
	cancelReply();

	showTypingIndicator();

	/**
	 * تابع داخلی ارسال واقعی متن به سرور، پس از اطمینان از وجود مکالمه.
	 *
	 * @return {void}
	 */
	function sendTextMessage() {
		ssApiRequest('/conversation/' + state.conversationUid + '/message', 'POST', {
			uid: state.conversationUid,
			message: text,
			reply_to: replyTo || ''
		}).then(function (data) {
			state.lastMessageId = Math.max(state.lastMessageId, data.message_id);
		}).catch(function (error) {
			hideTypingIndicator();
			console.error('خطا در ارسال پیام:', error);
			renderBotMessage('خطا در ارسال پیام رخ داد. لطفاً دوباره تلاش کنید.');
		});
	}

	if (!state.conversationId) {
		// اگر به هر دلیلی مکالمه هنوز ساخته نشده، ابتدا آن را می‌سازیم و سپس پیام را ارسال می‌کنیم
		initConversation(sendTextMessage);
	} else {
		sendTextMessage();
	}
}

// ریپلای زدن روی پیام خاص
function setReply(text) {
	var shortText = text.length > 40 ? text.substring(0, 40) + '...' : text;
	state.replyingTo = shortText;

	var preview = document.getElementById('reply-preview');
	var previewText = document.getElementById('reply-target-text');

	previewText.innerText = shortText;
	preview.classList.remove('hidden');
}

// لغو حالت ریپلای
function cancelReply() {
	state.replyingTo = null;
	document.getElementById('reply-preview').classList.add('hidden');
}

// نمایش وضعیت در حال تایپ
function showTypingIndicator() {
	var messagesBox = document.getElementById('chat-messages');
	var typingHTML = '<div id="typing-indicator" class="typing-bubble">' +
		'<div class="typing-dot"></div>' +
		'<div class="typing-dot"></div>' +
		'<div class="typing-dot"></div>' +
		'</div>';
	messagesBox.insertAdjacentHTML('beforeend', typingHTML);
	messagesBox.scrollTop = messagesBox.scrollHeight;
}

// پنهان کردن وضعیت در حال تایپ
function hideTypingIndicator() {
	var indicator = document.getElementById('typing-indicator');
	if (indicator) {
		indicator.remove();
	}
}

/**
 * ساخت امن یک المان متنی جهت جلوگیری از حملات XSS هنگام درج پیام کاربر در DOM.
 *
 * به‌جای innerHTML مستقیم با متن خام کاربر، از textContent استفاده می‌شود
 * تا هرگونه تگ HTML مخرب به صورت متن ساده نمایش داده شود، نه اجرا شود.
 *
 * @param {string} text متن خام.
 * @return {string} متن ایمن‌شده جهت درج در innerHTML.
 */
function escapeHtml(text) {
	var div = document.createElement('div');
	div.textContent = text;
	return div.innerHTML;
}

// اضافه کردن پیام کاربر به صفحه گفتگو (رندر امن در برابر XSS)
function renderUserMessage(text, replyTo) {
	var messagesBox = document.getElementById('chat-messages');
	var safeText = escapeHtml(text);
	var replyHTML = '';

	if (replyTo) {
		replyHTML = '<div class="reply-wrapper">' + escapeHtml(replyTo) + '</div>';
	}

	var messageHTML = '<div class="msg-wrapper user">' +
		'<div class="reply-btn" onclick="setReply(\'' + safeText.replace(/'/g, "\\'") + '\')">' +
		'<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 14 4 9 9 4"></polyline><path d="M20 20v-7a4 4 0 00-4-4H4"></path></svg>' +
		'</div>' +
		'<div class="msg-bubble-container">' + replyHTML + '<div class="msg-bubble">' + safeText + '</div></div>' +
		'</div>';

	messagesBox.insertAdjacentHTML('beforeend', messageHTML);
	messagesBox.scrollTop = messagesBox.scrollHeight;
}

// اضافه کردن پیام بات/اپراتور به صفحه گفتگو (رندر امن در برابر XSS)
function renderBotMessage(text) {
	var messagesBox = document.getElementById('chat-messages');
	var safeText = escapeHtml(text);

	var messageHTML = '<div class="msg-wrapper bot">' +
		'<div class="msg-bubble">' + safeText + '</div>' +
		'<div class="reply-btn" onclick="setReply(\'' + safeText.replace(/'/g, "\\'") + '\')">' +
		'<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 14 20 9 15 4"></polyline><path d="M4 20v-7a4 4 0 014-4h12"></path></svg>' +
		'</div>' +
		'</div>';

	messagesBox.insertAdjacentHTML('beforeend', messageHTML);
	messagesBox.scrollTop = messagesBox.scrollHeight;
}

/**
 * کلیک بر روی سوالات متداول (FAQ). طبق مستندات پروژه، با کلیک روی
 * هر FAQ، آن سوال به عنوان اولین پیام کاربر مستقیماً به سرور ارسال
 * می‌شود و کاربر بدون نیاز به دکمه «شروع چت» وارد محیط چت می‌شود.
 *
 * @param {string} question متن سوال متداول انتخاب‌شده.
 * @return {void}
 */
function handleFAQ(question) {
	startLiveChat();

	// علامت‌گذاری شروع پردازش FAQ تا پولینگ در این بازه موقتاً متوقف بماند
	state.isFaqPending = true;

	renderUserMessage(question, null);
	showTypingIndicator();

	/**
	 * تابع داخلی ارسال واقعی سوال به سرور. اگر مکالمه هنوز ساخته نشده
	 * باشد، ابتدا initConversation فراخوانی و منتظر تکمیل آن می‌مانیم
	 * تا از ارسال پیام با شناسه مکالمه خالی جلوگیری شود.
	 *
	 * @return {void}
	 */
	function sendFaqQuestion() {
		ssApiRequest('/conversation/' + state.conversationUid + '/message', 'POST', {
			uid: state.conversationUid,
			message: question,
			reply_to: '',
			is_faq: true
		}).then(function (data) {
			// آپدیت فوری (نه با تأخیر) تا پولینگ احتمالی همین لحظه این پیام را تکراری رندر نکند
			state.lastMessageId = Math.max(state.lastMessageId, data.message_id);
			if (data.auto_reply) {
				state.lastMessageId = Math.max(state.lastMessageId, data.auto_reply.id);
			}

			// کمی تأخیر مصنوعی فقط جهت حفظ حس طبیعی تایپینگ در نمایش بصری (بدون تأثیر بر state)
			setTimeout(function () {
				hideTypingIndicator();

				if (data.auto_reply) {
					renderBotMessage(data.auto_reply.message);
				} else {
					renderBotMessage('درخواست شما ثبت شد. همکاران ما به زودی پاسخ خواهند داد.');
				}

				state.isFaqPending = false;
			}, 1200);
		}).catch(function (error) {
			hideTypingIndicator();
			state.isFaqPending = false;
			console.error('خطا در ارسال سوال متداول:', error);
		});
	}

	if (!state.conversationId) {
		initConversation(sendFaqQuestion);
	} else {
		sendFaqQuestion();
	}
}

/* =========================================================================
 * بخش سیستم تیکت - جابجایی بین پنل‌ها
 * ========================================================================= */

// جابجایی بین پنل‌های مختلف سیستم تیکت
function changeTicketView(viewName) {
	state.ticketView = viewName;

	// لیست تمامی ویوها
	var views = ['menu', 'newStep1', 'newStep2', 'success', 'trackInput', 'trackResults', 'ticketDetail'];

	views.forEach(function (view) {
		var el = document.getElementById('ticket-' + view);
		if (view === viewName) {
			el.classList.remove('hidden');
		} else {
			el.classList.add('hidden');
		}
	});
}

/* =========================================================================
 * بخش ثبت تیکت جدید - اعتبارسنجی و ارسال به REST API
 * ========================================================================= */

/**
 * نمایش پیام خطا در باکس خطای مشخص‌شده.
 *
 * @param {string} elementId شناسه المان خطا.
 * @param {string} message متن پیام خطا.
 * @return {void}
 */
function showFormError(elementId, message) {
	var el = document.getElementById(elementId);
	el.textContent = message;
	el.classList.remove('hidden');
}

/**
 * پنهان کردن پیام خطای یک باکس مشخص.
 *
 * @param {string} elementId شناسه المان خطا.
 * @return {void}
 */
function hideFormError(elementId) {
	var el = document.getElementById(elementId);
	el.classList.add('hidden');
}

/**
 * اعتبارسنجی مرحله اول فرم تیکت (اطلاعات شخصی) قبل از رفتن به مرحله بعد.
 *
 * @return {void}
 */
function validateTicketStep1() {
	var firstName = document.getElementById('ticket-first-name').value.trim();
	var lastName = document.getElementById('ticket-last-name').value.trim();
	var phone = document.getElementById('ticket-phone').value.trim();

	if (!firstName || !lastName) {
		showFormError('ticket-step1-error', 'لطفاً نام و نام خانوادگی را وارد کنید.');
		return;
	}

	if (!/^09[0-9]{9}$/.test(phone)) {
		showFormError('ticket-step1-error', 'شماره تماس معتبر نیست (مثال: ۰۹۱۲XXXXXXX).');
		return;
	}

	hideFormError('ticket-step1-error');
	state.currentTicketPhone = phone;
	changeTicketView('newStep2');
}

/**
 * مدیریت انتخاب فایل ضمیمه در فرم تیکت (فقط انتخاب، آپلود واقعی هنگام ثبت نهایی انجام می‌شود).
 *
 * @param {Event} event رویداد تغییر input فایل.
 * @return {void}
 */
function handleTicketFileSelect(event) {
	var file = event.target.files[0];
	var label = document.getElementById('ticket-upload-label');
	var zone = document.getElementById('ticket-upload-zone');

	if (!file) {
		return;
	}

	// اعتبارسنجی سمت کلاینت حجم و فرمت فایل قبل از آپلود واقعی به سرور
	var allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
	var extension = file.name.split('.').pop().toLowerCase();

	if (allowedExtensions.indexOf(extension) === -1) {
		showFormError('ticket-step2-error', 'فرمت فایل مجاز نیست. فقط jpg، jpeg، png و pdf پذیرفته می‌شود.');
		event.target.value = '';
		return;
	}

	if (file.size > 5242880) {
		showFormError('ticket-step2-error', 'حجم فایل نباید بیشتر از ۵ مگابایت باشد.');
		event.target.value = '';
		return;
	}

	hideFormError('ticket-step2-error');
	label.textContent = file.name;
	zone.classList.add('has-file');
}

/**
 * اعتبارسنجی نهایی و ارسال فرم کامل تیکت به سرور (شامل آپلود فایل در صورت وجود).
 *
 * @return {void}
 */
function submitTicketForm() {
	var subject = document.getElementById('ticket-subject').value.trim();
	var message = document.getElementById('ticket-message').value.trim();
	var department = document.getElementById('ticket-department').value;
	var fileInput = document.getElementById('ticket-file-input');
	var submitBtn = document.getElementById('ticket-submit-btn');

	if (!subject || !message) {
		showFormError('ticket-step2-error', 'لطفاً عنوان و متن پیام را کامل وارد کنید.');
		return;
	}

	if (!department) {
		showFormError('ticket-step2-error', 'لطفاً واحد مربوطه را انتخاب کنید.');
		return;
	}

	hideFormError('ticket-step2-error');
	submitBtn.disabled = true;
	submitBtn.textContent = 'در حال ثبت...';

	var firstName = document.getElementById('ticket-first-name').value.trim();
	var lastName = document.getElementById('ticket-last-name').value.trim();
	var phone = document.getElementById('ticket-phone').value.trim();

	var formData = new FormData();
	formData.append('first_name', firstName);
	formData.append('last_name', lastName);
	formData.append('phone', phone);
	formData.append('subject', subject);
	formData.append('message', message);
	formData.append('department', department);

	if (fileInput.files[0]) {
		// نمایش نوار پیشرفت آپلود جهت بازخورد بصری به کاربر در حین ارسال فایل
		var progressWrap = document.getElementById('ticket-upload-progress-wrap');
		var progressBar = document.getElementById('ticket-upload-progress-bar');
		progressWrap.classList.remove('hidden');

		formData.append('attachment', fileInput.files[0]);

		submitTicketWithProgress(formData, progressBar, submitBtn);
	} else {
		submitTicketPlain(formData, submitBtn);
	}
}

/**
 * ارسال فرم تیکت بدون فایل ضمیمه از طریق fetch استاندارد.
 *
 * @param {FormData} formData داده‌های فرم.
 * @param {HTMLElement} submitBtn دکمه ثبت جهت بازگردانی وضعیت در صورت خطا.
 * @return {void}
 */
function submitTicketPlain(formData, submitBtn) {
	fetch(SS_Data.rest_url + '/ticket/submit', {
		method: 'POST',
		headers: {
			'X-WP-Nonce': SS_Data.nonce
		},
		body: formData
	}).then(function (response) {
		return response.json().then(function (data) {
			if (!response.ok) {
				throw new Error(data.message || 'خطا در ثبت تیکت رخ داد.');
			}
			return data;
		});
	}).then(function (data) {
		onTicketSubmitSuccess(data);
	}).catch(function (error) {
		submitBtn.disabled = false;
		submitBtn.textContent = 'ثبت نهایی تیکت';
		showFormError('ticket-step2-error', error.message);
	});
}

/**
 * ارسال فرم تیکت همراه با فایل ضمیمه با استفاده از XMLHttpRequest جهت
 * دریافت رویداد progress واقعی درصد آپلود (که با fetch امکان‌پذیر نیست).
 *
 * @param {FormData} formData داده‌های فرم شامل فایل.
 * @param {HTMLElement} progressBar المان نوار پیشرفت.
 * @param {HTMLElement} submitBtn دکمه ثبت جهت بازگردانی وضعیت در صورت خطا.
 * @return {void}
 */
function submitTicketWithProgress(formData, progressBar, submitBtn) {
	var xhr = new XMLHttpRequest();
	xhr.open('POST', SS_Data.rest_url + '/ticket/submit', true);
	xhr.setRequestHeader('X-WP-Nonce', SS_Data.nonce);

	xhr.upload.onprogress = function (event) {
		if (event.lengthComputable) {
			var percent = Math.round((event.loaded / event.total) * 100);
			progressBar.style.width = percent + '%';
		}
	};

	xhr.onload = function () {
		var data;
		try {
			data = JSON.parse(xhr.responseText);
		} catch (e) {
			data = {};
		}

		if (xhr.status >= 200 && xhr.status < 300) {
			onTicketSubmitSuccess(data);
		} else {
			submitBtn.disabled = false;
			submitBtn.textContent = 'ثبت نهایی تیکت';
			showFormError('ticket-step2-error', data.message || 'خطا در ثبت تیکت رخ داد.');
		}
	};

	xhr.onerror = function () {
		submitBtn.disabled = false;
		submitBtn.textContent = 'ثبت نهایی تیکت';
		showFormError('ticket-step2-error', 'خطا در ارتباط با سرور رخ داد.');
	};

	xhr.send(formData);
}

/**
 * پردازش موفقیت‌آمیز ثبت تیکت: نمایش کد پیگیری و بازگردانی وضعیت فرم.
 *
 * @param {Object} data پاسخ سرور شامل tracking_code.
 * @return {void}
 */
function onTicketSubmitSuccess(data) {
	var submitBtn = document.getElementById('ticket-submit-btn');
	submitBtn.disabled = false;
	submitBtn.textContent = 'ثبت نهایی تیکت';

	document.getElementById('ticket-success-code').textContent = data.tracking_code;
	changeTicketView('success');

	// بازنشانی کامل فرم جهت آماده‌سازی برای ثبت تیکت بعدی
	resetTicketForm();
}

/**
 * پاک‌سازی کامل فیلدهای فرم تیکت پس از ثبت موفق.
 *
 * @return {void}
 */
function resetTicketForm() {
	document.getElementById('ticket-first-name').value = '';
	document.getElementById('ticket-last-name').value = '';
	document.getElementById('ticket-phone').value = '';
	document.getElementById('ticket-subject').value = '';
	document.getElementById('ticket-message').value = '';
	document.getElementById('ticket-department').value = '';
	document.getElementById('ticket-file-input').value = '';
	document.getElementById('ticket-upload-label').textContent = 'آپلود عکس یا مدرک (PDF)';
	document.getElementById('ticket-upload-zone').classList.remove('has-file');
	document.getElementById('ticket-upload-progress-wrap').classList.add('hidden');
	document.getElementById('ticket-upload-progress-bar').style.width = '0%';
}

/* =========================================================================
 * بخش پیگیری تیکت - جستجو و نمایش جزئیات
 * ========================================================================= */

/**
 * جستجوی تیکت‌های ثبت‌شده با شماره تماس واردشده و نمایش لیست نتایج.
 *
 * @return {void}
 */
function trackTickets() {
	var phone = document.getElementById('track-phone-input').value.trim();

	if (!/^09[0-9]{9}$/.test(phone)) {
		showFormError('track-input-error', 'شماره تماس معتبر نیست (مثال: ۰۹۱۲XXXXXXX).');
		return;
	}

	hideFormError('track-input-error');
	state.currentTicketPhone = phone;

	ssApiRequest('/ticket/track', 'POST', { phone: phone }).then(function (data) {
		renderTicketList(data.tickets);
		changeTicketView('trackResults');
	}).catch(function (error) {
		showFormError('track-input-error', error.message);
	});
}

/**
 * تبدیل تاریخ میلادی ذخیره‌شده در دیتابیس به فرمت قابل‌نمایش ساده.
 *
 * @param {string} mysqlDate تاریخ به فرمت YYYY-MM-DD HH:MM:SS.
 * @return {string} تاریخ فرمت‌شده جهت نمایش.
 */
function formatDisplayDate(mysqlDate) {
	if (!mysqlDate) {
		return '';
	}
	return mysqlDate.split(' ')[0];
}

/**
 * تعیین کلاس CSS رنگی متناظر با وضعیت تیکت جهت هماهنگی با دیزاین موجود.
 *
 * @param {string} status وضعیت داخلی تیکت.
 * @return {string} نام کلاس CSS.
 */
function getStatusClass(status) {
	if (status === 'resolved') {
		return 'resolved';
	}
	if (status === 'closed') {
		return 'closed';
	}
	return 'pending';
}

/**
 * رندر لیست میناتوری تیکت‌های یافت‌شده در ستون نتایج جستجو.
 *
 * @param {Array} tickets آرایه تیکت‌های دریافتی از سرور.
 * @return {void}
 */
function renderTicketList(tickets) {
	var container = document.getElementById('ticket-list-container');
	container.innerHTML = '';

	if (!tickets || tickets.length === 0) {
		container.innerHTML = '<p class="ticket-desc text-center">هیچ تیکتی با این شماره تماس یافت نشد.</p>';
		return;
	}

	tickets.forEach(function (ticket) {
		var item = document.createElement('div');
		item.className = 'ticket-list-item';
		item.onclick = function () {
			openTicketDetail(ticket.id);
		};

		var statusClass = getStatusClass(ticket.status);

		item.innerHTML = '<div class="item-top">' +
			'<span class="item-title">' + escapeHtml(ticket.subject) + '</span>' +
			'<span class="status-tag ' + statusClass + '">' + escapeHtml(ticket.status_label) + '</span>' +
			'</div>' +
			'<div class="item-bottom">' +
			'<span>' + escapeHtml(ticket.tracking_code) + '</span>' +
			'<span>' + escapeHtml(formatDisplayDate(ticket.created_at)) + '</span>' +
			'</div>';

		container.appendChild(item);
	});
}

/**
 * دریافت جزئیات کامل یک تیکت و نمایش تاریخچه گفتگوی آن.
 *
 * @param {number} ticketId شناسه عددی تیکت.
 * @return {void}
 */
function openTicketDetail(ticketId) {
	ssApiRequest('/ticket/' + ticketId + '/detail', 'POST', { phone: state.currentTicketPhone })
		.then(function (data) {
			state.currentTicketId = ticketId;

			document.getElementById('ticket-detail-subject').textContent = data.ticket.subject;

			var statusTag = document.getElementById('ticket-detail-status');
			statusTag.textContent = data.ticket.status_label;
			statusTag.className = 'status-tag ' + getStatusClass(data.ticket.status);

			document.getElementById('ticket-detail-meta').textContent =
				'شناسه: ' + data.ticket.tracking_code + ' • تاریخ ثبت: ' + formatDisplayDate(data.ticket.created_at);

			renderTicketDetailMessages(data.messages);

			changeTicketView('ticketDetail');
		})
		.catch(function (error) {
			console.error('خطا در دریافت جزئیات تیکت:', error);
		});
}

/**
 * رندر تاریخچه پیام‌های یک تیکت در ناحیه جزئیات (تابع مستقل جهت استفاده مجدد پس از ارسال پیام جدید).
 *
 * @param {Array} messages آرایه پیام‌های تیکت.
 * @return {void}
 */
function renderTicketDetailMessages(messages) {
	var historyContainer = document.getElementById('ticket-detail-history');
	historyContainer.innerHTML = '';

	messages.forEach(function (msg) {
		var msgEl = document.createElement('div');
		msgEl.className = 'detail-msg ' + (msg.sender_type === 'user' ? 'user-side' : 'support-side');

		var textEl = document.createElement('p');
		textEl.textContent = msg.message;
		msgEl.appendChild(textEl);

		historyContainer.appendChild(msgEl);
	});
}

/**
 * بررسی کلید اینتر در باکس ارسال پیام جدید تیکت.
 *
 * @param {KeyboardEvent} event رویداد صفحه‌کلید.
 * @return {void}
 */
function checkTicketReplyEnter(event) {
	if (event.key === 'Enter') {
		sendTicketReply();
	}
}

/**
 * ارسال پیام جدید کاربر در ادامه گفتگوی تیکت باز، حتی پس از پاسخ‌داده‌شدن.
 *
 * @return {void}
 */
function sendTicketReply() {
	var input = document.getElementById('ticket-reply-input');
	var text = input.value.trim();

	if (!text || !state.currentTicketId) {
		return;
	}

	input.value = '';
	input.disabled = true;

	ssApiRequest('/ticket/' + state.currentTicketId + '/reply', 'POST', {
		phone: state.currentTicketPhone,
		message: text
	}).then(function () {
		input.disabled = false;
		input.focus();
		// بازخوانی کامل تاریخچه از سرور تا پیام جدید و وضعیت به‌روزشده تیکت نمایش داده شود
		return ssApiRequest('/ticket/' + state.currentTicketId + '/detail', 'POST', { phone: state.currentTicketPhone });
	}).then(function (data) {
		if (!data) {
			return;
		}
		var statusTag = document.getElementById('ticket-detail-status');
		statusTag.textContent = data.ticket.status_label;
		statusTag.className = 'status-tag ' + getStatusClass(data.ticket.status);
		renderTicketDetailMessages(data.messages);
	}).catch(function (error) {
		input.disabled = false;
		input.value = text;
		console.error('خطا در ارسال پیام تیکت:', error);
	});
}

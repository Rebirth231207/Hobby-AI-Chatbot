<?php
/**
 * قالب اصلی پنل مدیریت SPA پشتیبانی هوشمند.
 *
 * ساختار این فایل دقیقاً بر اساس فایل دیزاین ارائه‌شده پیاده‌سازی شده:
 * هدر بالا (لوگو + سوئیچ تم)، سایدبار ناوبری (۵ بخش)، و محتوای اصلی که
 * بین پنج بخش (چت زنده، تیکت‌ها، نالج گراف، FAQ، تنظیمات) جابجا می‌شود.
 * تمام محتوای پویا (لیست چت‌ها، پیام‌ها، تیکت‌ها و غیره) توسط
 * admin/js/admin.js از طریق REST API بارگذاری و رندر می‌شود.
 *
 * @package SmartSupport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap" style="margin: 10px 20px 0 2px;">

	<div id="ss-admin-root">

		<!-- ====== هدر بالای پنل ====== -->
		<div class="ss-header">
			<div class="ss-header-logo">
				<div class="ss-header-logo-icon">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"></path></svg>
				</div>
				<span class="ss-header-logo-text">پشتیبانی هوشمند</span>
			</div>
			<button type="button" class="ss-theme-toggle" id="ss-theme-toggle" title="تغییر پوسته">
				<svg id="ss-icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
				<svg id="ss-icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" style="display:none;"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"></path></svg>
			</button>
		</div>

		<!-- ====== بدنه اصلی ====== -->
		<div class="ss-body">

			<!-- ====== سایدبار ناوبری ====== -->
			<div class="ss-sidebar">
				<button type="button" class="ss-nav-item active" data-section="liveChat">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"></path></svg>
					<span>چت زنده</span>
					<span class="ss-nav-badge" id="ss-nav-unread-badge" style="display:none;">0</span>
				</button>
				<button type="button" class="ss-nav-item" data-section="tickets">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"></path></svg>
					<span>تیکت‌ها</span>
				</button>
				<button type="button" class="ss-nav-item" data-section="knowledge">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"></path></svg>
					<span>نالج گراف</span>
				</button>
				<button type="button" class="ss-nav-item" data-section="faqs">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M11 18h2v-2h-2v2zm1-16C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-14c-2.21 0-4 1.79-4 4h2c0-1.1.9-2 2-2s2 .9 2 2c0 2-3 1.75-3 5h2c0-2.25 3-2.5 3-5 0-2.21-1.79-4-4-4z"></path></svg>
					<span>سوالات متداول</span>
				</button>
				<button type="button" class="ss-nav-item" data-section="settings">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 00.12-.61l-1.92-3.32a.49.49 0 00-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.484.484 0 00-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.07.62-.07.94s.02.64.07.94l-2.03 1.58a.49.49 0 00-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"></path></svg>
					<span>تنظیمات</span>
				</button>

				<div class="ss-sidebar-spacer"></div>
				<div class="ss-sidebar-version">نسخه ۱.۰.۰</div>
			</div>

			<!-- ====== محتوای اصلی ====== -->
			<div class="ss-content">

				<!-- ========== بخش چت زنده ========== -->
				<div class="ss-section active" id="ss-section-liveChat">

					<!-- ستون راست: لیست چت‌ها -->
					<div class="ss-chat-list-col">
						<div class="ss-stats-row">
							<div class="ss-stat-card">
								<div class="ss-stat-value" id="ss-stat-unanswered" style="color:#ef4444;">0</div>
								<div class="ss-stat-label">بی‌پاسخ</div>
							</div>
							<div class="ss-stat-card">
								<div class="ss-stat-value" id="ss-stat-answered" style="color:#22c55e;">0</div>
								<div class="ss-stat-label">پاسخ‌داده</div>
							</div>
							<div class="ss-stat-card">
								<div class="ss-stat-value" id="ss-stat-closed">0</div>
								<div class="ss-stat-label">بسته‌شده</div>
							</div>
						</div>
						<div class="ss-filter-bar">
							<button type="button" class="ss-filter-btn" id="ss-filter-unread" data-filter="unread">فقط خوانده‌نشده</button>
							<button type="button" class="ss-filter-btn active" id="ss-filter-all" data-filter="all">همه چت‌ها</button>
						</div>
						<div class="ss-chat-list" id="ss-chat-list">
							<div class="ss-empty-table-row">در حال بارگذاری...</div>
						</div>
					</div>

					<!-- ستون وسط: پنجره گفتگو -->
					<div class="ss-chat-window-col" id="ss-chat-window-col">
						<div class="ss-empty-state">
							<svg width="64" height="64" viewBox="0 0 24 24" fill="var(--ss-text-muted)"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"></path></svg>
							<div class="ss-empty-state-text">یک گفتگو را از لیست انتخاب کنید</div>
						</div>
					</div>

					<!-- ستون چپ: پیام‌های آماده -->
					<div class="ss-canned-col">
						<div class="ss-canned-header">پیام‌های آماده</div>
						<div class="ss-canned-list" id="ss-canned-sidebar-list">
							<div class="ss-empty-table-row">در حال بارگذاری...</div>
						</div>
					</div>
				</div>

				<!-- ========== بخش تیکت‌ها ========== -->
				<div class="ss-section" id="ss-section-tickets">
					<!-- نمای لیست تیکت‌ها -->
					<div class="ss-page-padded" id="ss-tickets-list-view">
						<div class="ss-page-header">
							<div class="ss-page-title">مدیریت تیکت‌ها</div>
							<div class="ss-toolbar">
								<select class="ss-select-chip" id="ss-ticket-filter-department">
									<option value="">همه واحدها</option>
									<!-- گزینه‌های واحد به صورت داینامیک از تنظیمات پنل ادمین توسط جاوااسکریپت رندر می‌شوند -->
								</select>
								<select class="ss-select-chip" id="ss-ticket-filter-status">
									<option value="">همه وضعیت‌ها</option>
									<option value="open">باز</option>
									<option value="pending">در حال بررسی</option>
									<option value="user_replied">پاسخ جدید کاربر</option>
									<option value="resolved">پاسخ داده شده</option>
									<option value="closed">بسته شده</option>
								</select>
								<div class="ss-search-chip">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
									<input type="text" id="ss-ticket-search" placeholder="جستجو...">
								</div>
							</div>
						</div>
						<div class="ss-table-card">
							<div class="ss-table-header ss-tickets-grid">
								<div>کد پیگیری</div>
								<div>نام</div>
								<div>عنوان</div>
								<div>واحد</div>
								<div>وضعیت</div>
								<div>تاریخ</div>
							</div>
							<div class="ss-table-body" id="ss-tickets-table-body">
								<div class="ss-empty-table-row">در حال بارگذاری...</div>
							</div>
						</div>
					</div>

					<!-- نمای گفتگوی تیکت -->
					<div class="ss-chat-window-col" id="ss-ticket-chat-view" style="display:none;">
						<div class="ss-ticket-chat-header">
							<button type="button" class="ss-back-btn" id="ss-ticket-back-btn">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--ss-text-primary)" stroke-width="2.5" stroke-linecap="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
							</button>
							<div style="flex:1;">
								<div class="ss-ticket-chat-title" id="ss-ticket-chat-title">-</div>
								<div class="ss-ticket-chat-meta">
									<span class="ss-ticket-chat-code" id="ss-ticket-chat-code">-</span>
									<span class="ss-status-tag" id="ss-ticket-chat-status">-</span>
								</div>
							</div>
							<button type="button" class="ss-btn-add-knowledge" id="ss-ticket-add-knowledge-btn">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="#22c55e"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path></svg>
								Add
							</button>
						</div>
						<div class="ss-messages-area" id="ss-ticket-messages-area"></div>
						<div class="ss-chat-input-bar">
							<textarea class="ss-text-input" id="ss-ticket-reply-input" placeholder="پاسخ خود را بنویسید..." rows="1"></textarea>
							<button type="button" class="ss-send-btn" id="ss-ticket-reply-send">
								<svg width="18" height="18" viewBox="0 0 24 24" fill="white"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg>
							</button>
						</div>
					</div>
				</div>

				<!-- ========== بخش نالج گراف ========== -->
				<div class="ss-section" id="ss-section-knowledge">
					<div class="ss-page-padded">
						<div class="ss-page-header">
							<div class="ss-page-title">نالج گراف</div>
							<button type="button" class="ss-btn-primary" id="ss-add-knowledge-btn">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="white"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path></svg>
								افزودن گره جدید
							</button>
						</div>
						<div class="ss-table-card">
							<div class="ss-table-header ss-knowledge-grid">
								<div>عنوان/موضوع</div>
								<div>متن دانش پردازش‌شده</div>
								<div>برچسب‌ها</div>
							</div>
							<div class="ss-table-body" id="ss-knowledge-table-body">
								<div class="ss-empty-table-row">در حال بارگذاری...</div>
							</div>
						</div>
					</div>
				</div>

				<!-- ========== بخش سوالات متداول و پیام‌های آماده ========== -->
				<div class="ss-section" id="ss-section-faqs">
					<div class="ss-page-padded">
						<div class="ss-page-title" style="margin-bottom:20px;">سوالات متداول و پیام‌های آماده</div>
						<div class="ss-tabs-bar">
							<button type="button" class="ss-tab-btn active" id="ss-faq-tab-btn" data-faqtab="faq">سوالات متداول (FAQ)</button>
							<button type="button" class="ss-tab-btn" id="ss-canned-tab-btn" data-faqtab="canned">پیام‌های آماده</button>
						</div>

						<!-- تب FAQ -->
						<div id="ss-faq-tab-content">
							<div style="display:flex; justify-content:flex-end; margin-bottom:16px;">
								<button type="button" class="ss-btn-primary" id="ss-add-faq-btn">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="white"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path></svg>
									افزودن سوال
								</button>
							</div>
							<div class="ss-table-card">
								<div class="ss-table-header ss-faq-grid">
									<div>سوال</div>
									<div>وضعیت</div>
									<div>عملیات</div>
								</div>
								<div class="ss-table-body" id="ss-faq-table-body">
									<div class="ss-empty-table-row">در حال بارگذاری...</div>
								</div>
							</div>
						</div>

						<!-- تب پیام‌های آماده -->
						<div id="ss-canned-tab-content" style="display:none;">
							<div style="display:flex; justify-content:flex-end; margin-bottom:16px;">
								<button type="button" class="ss-btn-primary" id="ss-add-canned-btn">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="white"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path></svg>
									افزودن پیام
								</button>
							</div>
							<div class="ss-table-card">
								<div class="ss-table-header ss-canned-grid">
									<div>عنوان</div>
									<div>متن پیام</div>
									<div>دسته</div>
									<div>عملیات</div>
								</div>
								<div class="ss-table-body" id="ss-canned-table-body">
									<div class="ss-empty-table-row">در حال بارگذاری...</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- ========== بخش تنظیمات پیشرفته ========== -->
				<div class="ss-section" id="ss-section-settings">
					<div class="ss-page-padded">
						<div class="ss-page-title" style="margin-bottom:20px;">تنظیمات پیشرفته</div>
						<div class="ss-settings-tabs-bar">
							<button type="button" class="ss-settings-tab-btn active" data-settingstab="ui">تنظیمات ظاهری</button>
							<button type="button" class="ss-settings-tab-btn" data-settingstab="ai">تنظیمات هوش مصنوعی</button>
							<button type="button" class="ss-settings-tab-btn" data-settingstab="logic">منطق پاسخ‌دهی</button>
							<button type="button" class="ss-settings-tab-btn" data-settingstab="ticket">تنظیمات تیکت</button>
						</div>

						<!-- تب تنظیمات ظاهری -->
						<div id="ss-settings-ui-content" class="ss-settings-card">
							<div class="ss-form-group">
								<label class="ss-form-label">لوگوی چت‌باکس</label>
								<div class="ss-logo-upload-row">
									<div class="ss-logo-preview" id="ss-logo-preview">
										<svg width="36" height="36" viewBox="0 0 24 24" fill="var(--ss-text-muted)" opacity="0.5"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"></path></svg>
									</div>
									<button type="button" class="ss-media-select-btn" data-media-target="logo_url">انتخاب از رسانه</button>
								</div>
							</div>
							<div class="ss-form-group">
								<label class="ss-form-label">متن خوش‌آمدگویی</label>
								<textarea class="ss-form-textarea" id="ss-setting-welcome-text" style="min-height:70px;"></textarea>
							</div>
							<div class="ss-form-group">
								<label class="ss-form-label">متن شناور (Tooltip)</label>
								<input type="text" class="ss-form-input" id="ss-setting-tooltip-text">
							</div>
							<div class="ss-form-group">
								<label class="ss-form-label">لینک بله</label>
								<input type="text" class="ss-form-input ltr" id="ss-setting-bale-link" placeholder="https://ble.ir/...">
							</div>
							<div class="ss-social-row">
								<div class="ss-social-icon bale" id="ss-bale-icon-preview">
									<svg width="28" height="28" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path></svg>
								</div>
								<span class="ss-social-label">آیکون بله</span>
								<button type="button" class="ss-social-media-btn" data-media-target="bale_icon_url">انتخاب از رسانه</button>
							</div>
							<div class="ss-form-group">
								<label class="ss-form-label">لینک تلگرام</label>
								<input type="text" class="ss-form-input ltr" id="ss-setting-telegram-link" placeholder="https://t.me/...">
							</div>
							<div class="ss-social-row">
								<div class="ss-social-icon telegram" id="ss-telegram-icon-preview">
									<svg width="28" height="28" viewBox="0 0 24 24" fill="white"><path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"></path></svg>
								</div>
								<span class="ss-social-label">آیکون تلگرام</span>
								<button type="button" class="ss-social-media-btn" data-media-target="telegram_icon_url">انتخاب از رسانه</button>
							</div>
							<div class="ss-form-group">
								<label class="ss-form-label">لینک روبیکا</label>
								<input type="text" class="ss-form-input ltr" id="ss-setting-rubika-link" placeholder="لینک روبیکا را وارد کنید">
							</div>
							<div class="ss-social-row">
								<div class="ss-social-icon rubika" id="ss-rubika-icon-preview">
									<svg width="28" height="28" viewBox="0 0 24 24" fill="white"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
								</div>
								<span class="ss-social-label">آیکون روبیکا</span>
								<button type="button" class="ss-social-media-btn" data-media-target="rubika_icon_url">انتخاب از رسانه</button>
							</div>

							<div class="ss-form-actions">
								<button type="button" class="ss-btn-primary" id="ss-save-ui-settings" style="padding:12px 32px; font-size:14px;">ذخیره تنظیمات</button>
							</div>
						</div>

						<!-- تب تنظیمات هوش مصنوعی -->
						<div id="ss-settings-ai-content" class="ss-settings-card" style="display:none;">
							<div class="ss-form-group">
								<label class="ss-form-label">آدرس Base URL (ثابت و غیرقابل تغییر)</label>
								<input type="text" class="ss-form-input ltr" id="ss-setting-ai-base-url" disabled>
							</div>
							<div class="ss-form-group">
								<label class="ss-form-label">کلید API</label>
								<input type="text" class="ss-form-input ltr" id="ss-setting-ai-api-key" placeholder="sk-...">
							</div>
							<div class="ss-form-group">
								<label class="ss-form-label">نام مدل</label>
								<input type="text" class="ss-form-input ltr" id="ss-setting-ai-model" placeholder="gpt-5-mini">
							</div>
							<div class="ss-form-actions">
								<button type="button" class="ss-btn-outline" id="ss-save-ai-settings">ذخیره تنظیمات</button>
								<button type="button" class="ss-test-connection-link" id="ss-test-ai-connection">تست اتصال</button>
								<span class="ss-test-connection-result" id="ss-test-ai-result"></span>
							</div>
						</div>

						<!-- تب منطق پاسخ‌دهی -->
						<div id="ss-settings-logic-content" class="ss-settings-card" style="display:none;">
							<div>
								<div class="ss-form-label" style="margin-bottom:16px;">وضعیت پاسخ‌دهی</div>
								<div class="ss-radio-group" id="ss-response-mode-group">
									<div class="ss-radio-item" data-mode="human">
										<span class="ss-radio-label">فقط اپراتور انسانی</span>
										<div class="ss-radio-dot-outer"><div class="ss-radio-dot-inner"></div></div>
									</div>
									<div class="ss-radio-item" data-mode="ai">
										<span class="ss-radio-label">فقط هوش مصنوعی</span>
										<div class="ss-radio-dot-outer"><div class="ss-radio-dot-inner"></div></div>
									</div>
									<div class="ss-radio-item" data-mode="hybrid">
										<span class="ss-radio-label">هوش مصنوعی + اپراتور</span>
										<div class="ss-radio-dot-outer"><div class="ss-radio-dot-inner"></div></div>
									</div>
								</div>
							</div>
							<div class="ss-form-group">
								<label class="ss-form-label">تنظیمات لحن و کلمات ممنوعه (Negative Prompting)</label>
								<textarea class="ss-form-textarea" id="ss-setting-negative-prompt" style="min-height:120px;" placeholder="لحن صمیمی و محترمانه داشته باش. از کلمات عامیانه استفاده نکن..."></textarea>
							</div>
							<div>
								<div class="ss-hierarchy-title">قانون سلسله‌مراتب استخراج اطلاعات (ثابت و هاردکد شده)</div>
								<div class="ss-hierarchy-item">۱. محصولات ووکامرس (نام، قیمت، موجودی، لینک)</div>
								<div class="ss-hierarchy-item">۲. نالج گراف (گره‌های دانش ثبت‌شده)</div>
								<div class="ss-hierarchy-item">۳. مقالات و پست‌های سایت</div>
							</div>
							<div class="ss-form-actions">
								<button type="button" class="ss-btn-outline" id="ss-save-logic-settings">ذخیره تنظیمات</button>
							</div>
						</div>
						<!-- تب تنظیمات تیکت -->
						<div id="ss-settings-ticket-content" class="ss-settings-card" style="display:none;">
							<div class="ss-form-group">
								<label class="ss-form-label">واحدهای مربوطه تیکت</label>
								<div id="ss-departments-list" class="ss-departments-list">
									<!-- ردیف‌های واحد به صورت داینامیک توسط جاوااسکریپت رندر می‌شوند -->
								</div>
								<button type="button" class="ss-add-department-btn" id="ss-add-department-btn">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path></svg>
									افزودن واحد جدید
								</button>
							</div>
							<div class="ss-form-actions">
								<button type="button" class="ss-btn-primary" id="ss-save-ticket-settings" style="padding:12px 32px; font-size:14px;">ذخیره تنظیمات</button>
							</div>
						</div>
					</div>
				</div>

			</div>
		</div>

		<!-- ====== مودال عمومی ====== -->
		<div class="ss-modal-overlay" id="ss-modal-overlay">
			<div class="ss-modal-box">
				<div class="ss-modal-header">
					<div class="ss-modal-title" id="ss-modal-title">عنوان</div>
					<button type="button" class="ss-modal-close" id="ss-modal-close">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--ss-text-muted)" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
					</button>
				</div>
				<div class="ss-modal-body" id="ss-modal-body"></div>
				<div class="ss-modal-actions">
					<button type="button" class="ss-modal-btn-save" id="ss-modal-save">ذخیره</button>
					<button type="button" class="ss-modal-btn-cancel" id="ss-modal-cancel">انصراف</button>
				</div>
			</div>
		</div>

		<!-- ====== Toast اعلان‌ها ====== -->
		<div class="ss-toast-container" id="ss-toast-container"></div>

	</div>
</div>

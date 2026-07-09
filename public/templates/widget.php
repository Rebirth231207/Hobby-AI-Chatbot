<?php
/**
 * قالب HTML ویجت چت پشتیبانی هوشمند.
 *
 * توجه بسیار مهم: ساختار HTML این فایل عیناً و بدون هیچ تغییری از فایل
 * Front.zip (index.html) استخراج شده است. طبق دستور صریح پروژه، ظاهر و
 * دیزاین این ویجت نباید تغییر کند. تنها تفاوت نسبت به فایل اصلی این
 * است که بخش شبیه‌سازی صفحه سایت دمو (demo-bg) که صرفاً برای تست
 * مستقل HTML بوده، حذف شده چون در سایت واقعی وردپرس کاربردی ندارد.
 *
 * @package SmartSupport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
  <div class="fab-container">
    <div id="fab-tooltip" class="fab-tooltip">
      پشتیبانی آنلاین هابی تک
      <div class="tooltip-arrow"></div>
    </div>

    <div id="fab-button" class="fab-button" onclick="toggleWidget()">
      <!-- آیکون چت (حالت بسته) -->
      <svg id="icon-chat" class="fab-icon" width="28" height="28" viewBox="0 0 24 24" fill="white">
        <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"></path>
      </svg>
      <!-- آیکون بستن (حالت باز) -->
      <svg id="icon-close" class="fab-icon hidden" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
      </svg>
    </div>
  </div>

  <!-- پنجره پاپ‌آپ ابزار پشتیبانی -->
  <div id="support-widget" class="support-widget dark hidden">
    
    <!-- هدر پنجره -->
    <div class="widget-header">
      <div class="header-actions">
        <div class="action-btn" onclick="toggleWidget()" title="بستن">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </div>
        <div class="action-btn" onclick="toggleTheme()" title="تغییر تم">
          <svg id="theme-sun" class="hidden" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#fbbf24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
          <svg id="theme-moon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#c4b5fd" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"></path></svg>
        </div>
      </div>
      <div class="header-title">پشتیبانی هوشمند</div>
      <div class="header-avatar">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"></path></svg>
      </div>
    </div>

    <!-- تب‌ها (چت زنده / تیکت‌ها) -->
    <div class="widget-tabs">
      <div id="tab-live" class="tab-item active" onclick="switchTab('live')">پشتیبانی زنده</div>
      <div id="tab-ticket" class="tab-item" onclick="switchTab('ticket')">تیکت‌ها</div>
    </div>

    <!-- بدنه اصلی ویجت -->
    <div class="widget-body">
      
      <!-- محتوای پشتیبانی زنده -->
      <div id="content-live" class="tab-content">
        
        <!-- صفحه خوش‌آمدگویی و سوالات متداول -->
        <div id="live-welcome" class="live-welcome">
          <div class="welcome-banner">
            <p>پشتیبانی فروشگاه هابی تِک همراه شماست؛ چطور می‌تونم کمکتون کنم؟</p>
          </div>
          
          <div class="faq-grid" id="faq-grid">
            <!-- سوالات متداول به صورت داینامیک از دیتابیس (زیرمنوی FAQ در پنل ادمین) توسط جاوااسکریپت رندر می‌شوند -->
          </div>

          <button class="btn-primary" onclick="startLiveChat()">شروع چت آنلاین</button>
        </div>

        <!-- صفحه گفتگوی فعال چت زنده -->
        <div id="live-chat" class="live-chat hidden">
          <div class="chat-subheader">
            <div class="back-btn" onclick="backToWelcome()">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
            </div>
            <div class="status-indicator">
              <span class="status-dot"></span>
              <span class="status-text">پشتیبان آنلاین است</span>
            </div>
            <div style="width: 28px;"></div>
          </div>

          <!-- باکس پیام‌ها -->
          <div id="chat-messages" class="chat-messages">
            <!-- پیام‌ها توسط جاوااسکریپت در اینجا رندر می‌شوند -->
          </div>

          <!-- بخش ورود متن و ارسال -->
          <div class="chat-input-area">
            <!-- پیش‌نمایش ریپلای تلگرامی -->
            <div id="reply-preview" class="reply-preview hidden">
              <div class="reply-indicator"></div>
              <div class="reply-text-container">
                <span class="reply-title">پاسخ به</span>
                <span id="reply-target-text" class="reply-target">متن پیام اصلی</span>
              </div>
              <div class="reply-close" onclick="cancelReply()">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
              </div>
            </div>

            <div class="input-row">
              <div class="input-btn" title="ضمیمه کردن فایل">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"></path></svg>
              </div>
              <input type="text" id="message-input" class="message-input" placeholder="پیام خود را بنویسید..." onkeypress="checkEnter(event)">
              <div class="send-btn" onclick="sendMessage()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="white" style="transform: rotate(180deg);"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg>
              </div>
            </div>

            <!-- شبکه‌های اجتماعی سریع -->
            <div class="social-fast-links">
              <div class="social-icon-btn" title="تلگرام">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="#818cf8"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path></svg>
              </div>
              <div class="social-icon-btn" title="بله">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="#818cf8"><path d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"></path></svg>
              </div>
              <div class="social-icon-btn" title="ایمیل">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="#818cf8"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"></path></svg>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- محتوای سیستم تیکت‌ها -->
      <div id="content-ticket" class="tab-content hidden">
        <div class="ticket-container">
          
          <!-- منوی اصلی تیکت‌ها -->
          <div id="ticket-menu" class="ticket-view">
            <div class="ticket-header-icon">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="white"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 3c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm7 13H5v-.23c0-.62.28-1.2.76-1.58C7.47 15.82 9.64 15 12 15s4.53.82 6.24 2.19c.48.38.76.97.76 1.58V19z"></path></svg>
            </div>
            <h3>سیستم تیکت پشتیبانی</h3>
            <p class="ticket-desc">تیکت جدید ثبت کنید یا تیکت قبلی را پیگیری نمایید.</p>

            <div class="menu-action-card active-card" onclick="changeTicketView('newStep1')">
              <div class="card-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="white"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"></path></svg>
              </div>
              <div class="card-text">
                <h4>ثبت تیکت جدید</h4>
                <span>ارسال درخواست رسمی پشتیبانی</span>
              </div>
            </div>

            <div class="menu-action-card" onclick="changeTicketView('trackInput')">
              <div class="card-icon blue-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="#6366f1"><path d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"></path></svg>
              </div>
              <div class="card-text">
                <h4>پیگیری تیکت</h4>
                <span>مشاهده وضعیت تیکت‌های قبلی</span>
              </div>
            </div>
          </div>

          <!-- ثبت تیکت مرحله اول (اطلاعات فردی) -->
          <div id="ticket-newStep1" class="ticket-view hidden">
            <div class="back-link" onclick="changeTicketView('menu')">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
              بازگشت به منو
            </div>
            <h3>اطلاعات شخصی</h3>
            <div class="form-group">
              <label>نام</label>
              <input type="text" id="ticket-first-name" placeholder="نام خود را وارد کنید">
            </div>
            <div class="form-group">
              <label>نام خانوادگی</label>
              <input type="text" id="ticket-last-name" placeholder="نام خانوادگی خود را وارد کنید">
            </div>
            <div class="form-group">
              <label>شماره تماس</label>
              <input type="text" id="ticket-phone" placeholder="۰۹۱۲XXXXXXX" style="direction: ltr; text-align: right;">
            </div>
            <div id="ticket-step1-error" class="ss-form-error hidden"></div>
            <button class="btn-primary" onclick="validateTicketStep1()">مرحله بعد</button>
          </div>

          <!-- ثبت تیکت مرحله دوم (جزئیات تیکت) -->
          <div id="ticket-newStep2" class="ticket-view hidden">
            <div class="back-link" onclick="changeTicketView('newStep1')">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
              مرحله قبل
            </div>
            <h3>جزئیات تیکت</h3>
            <div class="form-group">
              <label>عنوان تیکت</label>
              <input type="text" id="ticket-subject" placeholder="عنوان درخواست خود را بنویسید">
            </div>
            <div class="form-group">
              <label>متن پیام</label>
              <textarea id="ticket-message" placeholder="توضیحات خود را بنویسید..." rows="4"></textarea>
            </div>
            <div class="form-group">
              <label>واحد مربوطه</label>
              <select class="custom-select" id="ticket-department">
                <option value="" disabled selected>انتخاب کنید</option>
                <!-- گزینه‌های واحد به صورت داینامیک از تنظیمات پنل ادمین توسط جاوااسکریپت رندر می‌شوند -->
              </select>
            </div>
            <div class="upload-zone" id="ticket-upload-zone" onclick="document.getElementById('ticket-file-input').click()">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"></path></svg>
              <span id="ticket-upload-label">آپلود عکس یا مدرک (PDF)</span>
              <div id="ticket-upload-progress-wrap" class="ss-upload-progress-wrap hidden">
                <div id="ticket-upload-progress-bar" class="ss-upload-progress-bar"></div>
              </div>
            </div>
            <input type="file" id="ticket-file-input" accept=".jpg,.jpeg,.png,.pdf" class="hidden" onchange="handleTicketFileSelect(event)">
            <div id="ticket-step2-error" class="ss-form-error hidden"></div>
            <button class="btn-primary" id="ticket-submit-btn" onclick="submitTicketForm()">ثبت نهایی تیکت</button>
          </div>

          <!-- ثبت موفقیت‌آمیز تیکت -->
          <div id="ticket-success" class="ticket-view hidden">
            <div class="success-icon-wrap">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="white"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"></path></svg>
            </div>
            <h3>تیکت با موفقیت ثبت شد</h3>
            <p class="ticket-desc">کد پیگیری شما جهت مراجعات بعدی:</p>
            <div class="tracking-code-badge" id="ticket-success-code">HBT-00000</div>
            <div class="back-to-menu-link" onclick="changeTicketView('menu')">بازگشت به منوی تیکت‌ها</div>
          </div>

          <!-- پیگیری تیکت (ورود شماره موبایل) -->
          <div id="ticket-trackInput" class="ticket-view hidden">
            <div class="back-link" onclick="changeTicketView('menu')">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
              بازگشت به منو
            </div>
            <div class="center-icon">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="#6366f1"><path d="M15.5 1h-8C6.12 1 5 2.12 5 3.5v17C5 21.88 6.12 23 7.5 23h8c1.38 0 2.5-1.12 2.5-2.5v-17C18 2.12 16.88 1 15.5 1zm-4 21c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm4.5-4H7V4h9v14z"></path></svg>
            </div>
            <p class="ticket-desc text-center">شماره تماسی که با آن ثبت تیکت کرده‌اید را وارد کنید:</p>
            <div class="form-group">
              <input type="text" id="track-phone-input" placeholder="۰۹۱۲XXXXXXX" style="direction: ltr; text-align: center;">
            </div>
            <div id="track-input-error" class="ss-form-error hidden"></div>
            <button class="btn-primary" onclick="trackTickets()">جستجوی تیکت‌ها</button>
          </div>

          <!-- نتایج جستجوی تیکت‌ها -->
          <div id="ticket-trackResults" class="ticket-view hidden">
            <div class="back-link" onclick="changeTicketView('trackInput')">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
              تغییر شماره جستجو
            </div>
            <h3>تیکت‌های شما</h3>

            <div class="ticket-list" id="ticket-list-container">
              <!-- لیست تیکت‌ها توسط جاوااسکریپت و بر اساس داده واقعی سرور رندر می‌شود -->
            </div>
          </div>

          <!-- جزئیات تیکت و پیام‌ها -->
          <div id="ticket-ticketDetail" class="ticket-view hidden">
            <div class="back-link" onclick="changeTicketView('trackResults')">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
              بازگشت به لیست
            </div>
            <div class="detail-header">
              <h3 id="ticket-detail-subject">عنوان تیکت</h3>
              <span class="status-tag pending" id="ticket-detail-status">در حال بررسی</span>
            </div>
            <div class="detail-meta" id="ticket-detail-meta">شناسه: - • تاریخ ثبت: -</div>

            <div class="detail-history" id="ticket-detail-history">
              <!-- تاریخچه پیام‌های تیکت توسط جاوااسکریپت و بر اساس داده واقعی سرور رندر می‌شود -->
            </div>

            <!-- بخش ارسال پیام جدید در ادامه گفتگوی تیکت (حتی پس از پاسخ‌داده‌شدن) -->
            <div class="input-row ticket-reply-row">
              <input type="text" id="ticket-reply-input" class="message-input" placeholder="پیام خود را بنویسید..." onkeypress="checkTicketReplyEnter(event)">
              <div class="send-btn" onclick="sendTicketReply()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="white" style="transform: rotate(180deg);"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path></svg>
              </div>
            </div>
          </div>

        </div>
      </div>

    </div>
  </div>



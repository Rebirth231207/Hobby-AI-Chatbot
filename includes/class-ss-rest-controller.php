<?php
/**
 * کلاس کنترلر REST API افزونه پشتیبانی هوشمند.
 *
 * تمام Endpointهای مربوط به چت زنده و سیستم تیکت در این کلاس تعریف
 * می‌شوند. namespace اختصاصی افزونه «smart-support/v1» است. تمام
 * ورودی‌ها با schema اعتبارسنجی و sanitize می‌شوند و خروجی‌ها با
 * rest_ensure_response بازگردانده می‌شوند.
 *
 * @package SmartSupport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SS_REST_Controller {

	/**
	 * نام‌فضای اختصاصی REST API افزونه.
	 *
	 * @var string
	 */
	const NAMESPACE_NAME = 'smart-support/v1';

	/**
	 * ثبت تمام مسیرهای REST API افزونه.
	 *
	 * @return void
	 */
	public function register_routes() {

		/* -----------------------------------------------------------
		 * مسیر عمومی سوالات متداول فعال (نمایش در ویجت فرانت)
		 * ----------------------------------------------------------- */

		register_rest_route(
			self::NAMESPACE_NAME,
			'/faqs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_active_faqs' ),
				'permission_callback' => '__return_true',
			)
		);

		/* -----------------------------------------------------------
		 * مسیر عمومی واحدهای تیکت (نمایش در دراپ‌داون فرم ثبت تیکت)
		 * ----------------------------------------------------------- */

		register_rest_route(
			self::NAMESPACE_NAME,
			'/departments',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_departments_public' ),
				'permission_callback' => '__return_true',
			)
		);

		/* -----------------------------------------------------------
		 * مسیرهای چت زنده (Live Chat)
		 * ----------------------------------------------------------- */

		// شروع یک مکالمه جدید یا بازیابی مکالمه موجود بر اساس شناسه یکتا
		register_rest_route(
			self::NAMESPACE_NAME,
			'/conversation/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start_conversation' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'conversation_uid' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'guest_name'       => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'guest_phone'      => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// ارسال یک پیام جدید در یک مکالمه (متن + امکان پیوست فایل)
		register_rest_route(
			self::NAMESPACE_NAME,
			'/conversation/(?P<uid>[a-zA-Z0-9_\-]+)/message',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_message' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'uid'     => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'message' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'reply_to' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'is_faq'  => array(
						'required'          => false,
						'type'              => 'boolean',
						'default'           => false,
					),
				),
			)
		);

		// دریافت پیام‌های جدید یک مکالمه (برای polling سمت فرانت جهت شبیه‌سازی real-time)
		register_rest_route(
			self::NAMESPACE_NAME,
			'/conversation/(?P<uid>[a-zA-Z0-9_\-]+)/messages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_messages' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'uid'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'after_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		/* -----------------------------------------------------------
		 * مسیرهای سیستم تیکت (Tickets)
		 * ----------------------------------------------------------- */

		// ثبت تیکت جدید
		register_rest_route(
			self::NAMESPACE_NAME,
			'/ticket/submit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit_ticket' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'first_name' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'last_name'  => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'phone'      => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'subject'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'message'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'department' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// پیگیری تیکت‌ها بر اساس شماره تماس
		register_rest_route(
			self::NAMESPACE_NAME,
			'/ticket/track',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'track_tickets' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'phone' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// دریافت جزئیات و تاریخچه یک تیکت خاص
		register_rest_route(
			self::NAMESPACE_NAME,
			'/ticket/(?P<id>\d+)/detail',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'get_ticket_detail' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'    => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'phone' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// ارسال پیام جدید توسط کاربر در ادامه گفتگوی یک تیکت (حتی پس از پاسخ‌داده‌شدن)
		register_rest_route(
			self::NAMESPACE_NAME,
			'/ticket/(?P<id>\d+)/reply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reply_to_ticket' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'      => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'phone'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'message' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		/* -----------------------------------------------------------
		 * مسیر آپلود فایل مشترک (چت و تیکت)
		 * ----------------------------------------------------------- */

		register_rest_route(
			self::NAMESPACE_NAME,
			'/upload',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upload_file' ),
				'permission_callback' => '__return_true',
			)
		);

		/* -----------------------------------------------------------
		 * مسیرهای اختصاصی پنل مدیریت (نیازمند دسترسی manage_options)
		 * ----------------------------------------------------------- */

		// دریافت لیست تمام مکالمات چت زنده (ستون راست پنل ادمین)
		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/conversations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'admin_get_conversations' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'unread_only' => array(
						'required' => false,
						'type'     => 'boolean',
						'default'  => false,
					),
				),
			)
		);

		// دریافت پیام‌های یک مکالمه خاص (پنجره گفتگو پنل ادمین) + علامت‌گذاری خوانده‌شده
		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/conversations/(?P<id>\d+)/messages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'admin_get_conversation_messages' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'id'       => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'after_id' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// ارسال پاسخ ادمین در یک مکالمه
		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/conversations/(?P<id>\d+)/reply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'admin_reply_conversation' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'id'      => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'message' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		// خلاصه‌سازی و تزریق لاگ چت به نالج گراف («Add» سبز رنگ)
		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/conversations/(?P<id>\d+)/add-to-knowledge',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'admin_add_conversation_to_knowledge' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// دریافت لیست تمام تیکت‌ها با فیلتر (جدول گرید پنل ادمین)
		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/tickets',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'admin_get_tickets' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'department' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'status'     => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'search'     => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// دریافت جزئیات کامل یک تیکت جهت پنل ادمین (بدون نیاز به شماره تماس)
		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/tickets/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'admin_get_ticket_detail' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// ارسال پاسخ ادمین در یک تیکت
		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/tickets/(?P<id>\d+)/reply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'admin_reply_ticket' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'id'      => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'message' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		// خلاصه‌سازی و تزریق لاگ تیکت به نالج گراف
		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/tickets/(?P<id>\d+)/add-to-knowledge',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'admin_add_ticket_to_knowledge' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// آمار لحظه‌ای چت‌ها (بی‌پاسخ، پاسخ‌داده‌شده، بسته‌شده)
		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'admin_get_stats' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		/* -----------------------------------------------------------
		 * مسیرهای CRUD نالج گراف
		 * ----------------------------------------------------------- */

		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/knowledge',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'admin_get_knowledge_list' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'admin_save_knowledge' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_knowledge_args(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/knowledge/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'admin_save_knowledge' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_knowledge_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'admin_delete_knowledge' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		/* -----------------------------------------------------------
		 * مسیرهای CRUD سوالات متداول (FAQs)
		 * ----------------------------------------------------------- */

		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/faqs',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'admin_get_faqs_list' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'admin_save_faq' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_faq_args(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/faqs/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'admin_save_faq' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_faq_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'admin_delete_faq' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		/* -----------------------------------------------------------
		 * مسیرهای CRUD پیام‌های آماده (Canned Responses)
		 * ----------------------------------------------------------- */

		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/canned',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'admin_get_canned_list' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'admin_save_canned' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_canned_args(),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/canned/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'admin_save_canned' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_canned_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'admin_delete_canned' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		/* -----------------------------------------------------------
		 * مسیرهای تنظیمات پیشرفته (سه تب: ظاهری، هوش مصنوعی، منطق پاسخ‌دهی)
		 * ----------------------------------------------------------- */

		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/settings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'admin_get_settings' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/settings/ui',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'admin_save_settings_ui' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/settings/ai',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'admin_save_settings_ai' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/settings/logic',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'admin_save_settings_logic' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// تست زنده اتصال به سرویس هوش مصنوعی
		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/settings/ai/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'admin_test_ai_connection' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// ذخیره لیست کامل واحدهای تیکت (افزودن/ویرایش/حذف/ترتیب) از تب تنظیمات ظاهری
		register_rest_route(
			self::NAMESPACE_NAME,
			'/admin/settings/departments',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'admin_save_departments' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * بررسی سطح دسترسی مدیریتی برای تمام Endpointهای اختصاصی پنل ادمین.
	 *
	 * @return bool
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * تعریف schema مشترک آرگومان‌های نالج گراف (استفاده در ثبت و ویرایش).
	 *
	 * @return array
	 */
	private function get_knowledge_args() {
		return array(
			'title'   => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'tags'    => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * تعریف schema مشترک آرگومان‌های سوالات متداول.
	 *
	 * @return array
	 */
	private function get_faq_args() {
		return array(
			'question' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'answer'   => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'status'   => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => 'active',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * تعریف schema مشترک آرگومان‌های پیام‌های آماده.
	 *
	 * @return array
	 */
	private function get_canned_args() {
		return array(
			'title'    => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'message'  => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'category' => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/* =====================================================================
	 * پیاده‌سازی متدهای چت زنده
	 * ===================================================================== */

	/**
	 * شروع یک مکالمه جدید یا بازیابی مکالمه موجود بر اساس شناسه یکتای فرانت.
	 *
	 * شناسه یکتا (conversation_uid) در سمت فرانت با sessionStorage یا
	 * localStorage نگهداری می‌شود تا در صورت رفرش صفحه، مکالمه از دست نرود.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response
	 */
	public function start_conversation( WP_REST_Request $request ) {
		$conversation_uid = $request->get_param( 'conversation_uid' );

		// بررسی وجود مکالمه قبلی با همین شناسه (جهت حفظ Session)
		$existing = SS_Database::get_conversation_by_uid( $conversation_uid );

		if ( $existing ) {
			$messages = SS_Database::get_messages( $existing->id );

			return rest_ensure_response(
				array(
					'success'         => true,
					'conversation_id' => (int) $existing->id,
					'status'          => $existing->status,
					'messages'        => $this->format_messages( $messages ),
					'is_new'          => false,
				)
			);
		}

		// ساخت مکالمه جدید و ثبت اطلاعات کاربر لاگین‌شده (در صورت وجود) یا مهمان
		$current_user_id = get_current_user_id();

		$conversation_id = SS_Database::create_conversation(
			array(
				'conversation_uid' => $conversation_uid,
				'user_id'          => $current_user_id,
				'guest_name'       => $request->get_param( 'guest_name' ),
				'guest_phone'      => $request->get_param( 'guest_phone' ),
			)
		);

		if ( false === $conversation_id ) {
			return new WP_Error( 'ss_db_error', 'خطا در ایجاد مکالمه جدید.', array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success'         => true,
				'conversation_id' => $conversation_id,
				'status'          => 'unanswered',
				'messages'        => array(),
				'is_new'          => true,
			)
		);
	}

	/**
	 * دریافت لیست سوالات متداول فعال جهت نمایش کپسولی در ویجت فرانت.
	 *
	 * فقط سوالاتی که ادمین در پنل مدیریت وضعیت آن‌ها را «فعال» تنظیم
	 * کرده بازگردانده می‌شوند. اگر ادمین سوالی تعریف نکرده باشد، آرایه
	 * خالی برمی‌گردد و فرانت هیچ دکمه‌ای نمایش نمی‌دهد.
	 *
	 * @return WP_REST_Response
	 */
	public function get_active_faqs() {
		$items = SS_Database::get_all_faqs( true );

		$formatted = array();

		if ( ! empty( $items ) ) {
			foreach ( $items as $item ) {
				$formatted[] = array(
					'question' => $item->question,
					'answer'   => $item->answer,
				);
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'faqs'    => $formatted,
			)
		);
	}

	/**
	 * ارسال یک پیام جدید در یک مکالمه توسط کاربر (سمت فرانت‌اند).
	 *
	 * در صورتی که پیام یک سوال متداول (FAQ) باشد، بلافاصله پاسخ آماده
	 * متناظر (خوانده‌شده از دیتابیس نالج/FAQ مدیریت‌شده در پنل ادمین)
	 * نیز به عنوان پیام سیستم (system) در همان مکالمه ثبت می‌شود تا
	 * کاربر بدون تأخیر واقعی سرور، پاسخ را دریافت کند.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_message( WP_REST_Request $request ) {
		$conversation_uid = $request->get_param( 'uid' );
		$message_text      = $request->get_param( 'message' );
		$reply_to          = $request->get_param( 'reply_to' );
		$is_faq            = (bool) $request->get_param( 'is_faq' );

		$conversation = SS_Database::get_conversation_by_uid( $conversation_uid );

		if ( ! $conversation ) {
			return new WP_Error( 'ss_not_found', 'مکالمه مورد نظر یافت نشد.', array( 'status' => 404 ) );
		}

		if ( '' === trim( $message_text ) ) {
			return new WP_Error( 'ss_empty_message', 'متن پیام نمی‌تواند خالی باشد.', array( 'status' => 400 ) );
		}

		$message_id = SS_Database::insert_message(
			array(
				'conversation_id' => $conversation->id,
				'sender_type'     => 'user',
				'message'         => $message_text,
				'reply_to_text'   => $reply_to,
			)
		);

		if ( false === $message_id ) {
			return new WP_Error( 'ss_db_error', 'خطا در ثبت پیام.', array( 'status' => 500 ) );
		}

		$auto_reply = null;

		// در صورتی که پیام یک سوال متداول فعال باشد، پاسخ متناظر آن از دیتابیس خوانده و بلافاصله ثبت می‌شود
		if ( $is_faq ) {
			$faq = SS_Database::get_faq_by_question( $message_text );

			if ( $faq ) {
				$auto_reply_text = $faq->answer;

				$auto_reply_id = SS_Database::insert_message(
					array(
						'conversation_id' => $conversation->id,
						'sender_type'     => 'system',
						'message'         => $auto_reply_text,
					)
				);

				if ( false !== $auto_reply_id ) {
					$auto_reply = array(
						'id'      => $auto_reply_id,
						'message' => $auto_reply_text,
					);
				}

				// چون پاسخ خودکار سیستم ارائه شد، وضعیت مکالمه «پاسخ‌داده‌شده» ثبت می‌شود
				SS_Database::update_conversation_status( $conversation->id, 'answered' );
			} elseif ( 'unanswered' !== $conversation->status ) {
				SS_Database::update_conversation_status( $conversation->id, 'unanswered' );
			} else {
				SS_Database::touch_conversation( $conversation->id );
			}
		} elseif ( 'unanswered' !== $conversation->status ) {
			// اگر مکالمه بسته یا پاسخ‌داده‌شده بود، با پیام جدید کاربر دوباره به حالت «بی‌پاسخ» برمی‌گردد
			SS_Database::update_conversation_status( $conversation->id, 'unanswered' );
		} else {
			SS_Database::touch_conversation( $conversation->id );
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'message_id' => $message_id,
				'auto_reply' => $auto_reply,
			)
		);
	}

	/**
	 * دریافت پیام‌های جدید یک مکالمه (استفاده در چرخه Polling فرانت‌اند).
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_messages( WP_REST_Request $request ) {
		$conversation_uid = $request->get_param( 'uid' );
		$after_id          = $request->get_param( 'after_id' );

		$conversation = SS_Database::get_conversation_by_uid( $conversation_uid );

		if ( ! $conversation ) {
			return new WP_Error( 'ss_not_found', 'مکالمه مورد نظر یافت نشد.', array( 'status' => 404 ) );
		}

		$messages = SS_Database::get_messages( $conversation->id, $after_id );

		return rest_ensure_response(
			array(
				'success'  => true,
				'status'   => $conversation->status,
				'messages' => $this->format_messages( $messages ),
			)
		);
	}

	/**
	 * تبدیل نتایج خام دیتابیس پیام‌ها به فرمت مناسب خروجی JSON.
	 *
	 * @param array $messages آرایه‌ای از اشیاء خام دیتابیس.
	 * @return array آرایه‌ای از پیام‌های فرمت‌شده.
	 */
	private function format_messages( $messages ) {
		$formatted = array();

		if ( empty( $messages ) ) {
			return $formatted;
		}

		foreach ( $messages as $msg ) {
			$formatted[] = array(
				'id'             => (int) $msg->id,
				'sender_type'    => $msg->sender_type,
				'message'        => $msg->message,
				'attachment_url' => $msg->attachment_url,
				'reply_to_text'  => $msg->reply_to_text,
				'created_at'     => $msg->created_at,
			);
		}

		return $formatted;
	}

	/* =====================================================================
	 * پیاده‌سازی متدهای سیستم تیکت
	 * ===================================================================== */

	/**
	 * ثبت تیکت جدید.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_ticket( WP_REST_Request $request ) {

		// اعتبارسنجی شماره تماس با یک الگوی ساده موبایل ایران
		$phone = $request->get_param( 'phone' );

		if ( ! preg_match( '/^09[0-9]{9}$/', $phone ) ) {
			return new WP_Error( 'ss_invalid_phone', 'شماره تماس وارد شده معتبر نیست.', array( 'status' => 400 ) );
		}

		$department = $request->get_param( 'department' );

		if ( ! SS_Settings::is_valid_department( $department ) ) {
			return new WP_Error( 'ss_invalid_department', 'واحد انتخاب‌شده معتبر نیست.', array( 'status' => 400 ) );
		}

		$attachment_url = '';

		// پیوست اختیاری فایل به تیکت (در صورت ارسال به همراه فرم به صورت multipart)
		if ( ! empty( $_FILES['attachment'] ) ) {
			$upload_result = SS_File_Handler::handle_upload( $_FILES['attachment'] );

			if ( is_wp_error( $upload_result ) ) {
				return $upload_result;
			}

			$attachment_url = $upload_result['url'];
		}

		$ticket_data = array(
			'user_id'        => get_current_user_id(),
			'first_name'     => $request->get_param( 'first_name' ),
			'last_name'      => $request->get_param( 'last_name' ),
			'phone'          => $phone,
			'subject'        => $request->get_param( 'subject' ),
			'message'        => $request->get_param( 'message' ),
			'department'     => $department,
			'attachment_url' => $attachment_url,
		);

		$result = SS_Database::create_ticket( $ticket_data );

		if ( false === $result ) {
			return new WP_Error( 'ss_db_error', 'خطا در ثبت تیکت. لطفاً دوباره تلاش کنید.', array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success'       => true,
				'ticket_id'     => $result['id'],
				'tracking_code' => $result['tracking_code'],
			)
		);
	}

	/**
	 * دریافت لیست میناتوری تیکت‌های ثبت‌شده با یک شماره تماس مشخص.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response
	 */
	public function track_tickets( WP_REST_Request $request ) {
		$phone = $request->get_param( 'phone' );

		$tickets = SS_Database::get_tickets_by_phone( $phone );

		$formatted = array();

		if ( ! empty( $tickets ) ) {
			foreach ( $tickets as $ticket ) {
				$formatted[] = array(
					'id'            => (int) $ticket->id,
					'tracking_code' => $ticket->tracking_code,
					'subject'       => $ticket->subject,
					'status'        => $ticket->status,
					'status_label'  => $this->get_status_label( $ticket->status ),
					'created_at'    => $ticket->created_at,
				);
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'tickets' => $formatted,
			)
		);
	}

	/**
	 * دریافت جزئیات کامل یک تیکت شامل تاریخچه پیام‌ها.
	 *
	 * دسترسی به این متد فقط با ارائه صحیح شماره تماس مالک تیکت امکان‌پذیر
	 * است تا از افشای اطلاعات تیکت‌های دیگران جلوگیری شود.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_ticket_detail( WP_REST_Request $request ) {
		$ticket_id = $request->get_param( 'id' );
		$phone     = $request->get_param( 'phone' );

		$ticket = SS_Database::get_ticket_by_id_and_phone( $ticket_id, $phone );

		if ( ! $ticket ) {
			return new WP_Error( 'ss_not_found', 'تیکت یافت نشد یا شماره تماس مطابقت ندارد.', array( 'status' => 404 ) );
		}

		$messages = SS_Database::get_ticket_messages( $ticket_id );

		$formatted_messages = array();

		if ( ! empty( $messages ) ) {
			foreach ( $messages as $msg ) {
				$formatted_messages[] = array(
					'sender_type'    => $msg->sender_type,
					'message'        => $msg->message,
					'attachment_url' => $msg->attachment_url,
					'created_at'     => $msg->created_at,
				);
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'ticket'  => array(
					'id'            => (int) $ticket->id,
					'tracking_code' => $ticket->tracking_code,
					'subject'       => $ticket->subject,
					'status'        => $ticket->status,
					'status_label'  => $this->get_status_label( $ticket->status ),
					'created_at'    => $ticket->created_at,
				),
				'messages' => $formatted_messages,
			)
		);
	}

	/**
	 * ارسال پیام جدید توسط کاربر در ادامه گفتگوی یک تیکت.
	 *
	 * حتی پس از پاسخ‌داده‌شدن یا بسته‌شدن تیکت توسط ادمین، کاربر
	 * می‌تواند دوباره پیام بفرستد؛ در این صورت وضعیت تیکت به «در حال
	 * بررسی» بازمی‌گردد تا در پنل ادمین به‌عنوان تیکت نیازمند پیگیری
	 * دوباره مشخص شود. دسترسی فقط با تطبیق صحیح شماره تماس مالک تیکت
	 * امکان‌پذیر است.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reply_to_ticket( WP_REST_Request $request ) {
		$ticket_id = $request->get_param( 'id' );
		$phone     = $request->get_param( 'phone' );
		$message   = $request->get_param( 'message' );

		$ticket = SS_Database::get_ticket_by_id_and_phone( $ticket_id, $phone );

		if ( ! $ticket ) {
			return new WP_Error( 'ss_not_found', 'تیکت یافت نشد یا شماره تماس مطابقت ندارد.', array( 'status' => 404 ) );
		}

		if ( '' === trim( $message ) ) {
			return new WP_Error( 'ss_empty_message', 'متن پیام نمی‌تواند خالی باشد.', array( 'status' => 400 ) );
		}

		$message_id = SS_Database::insert_ticket_message(
			array(
				'ticket_id'   => $ticket_id,
				'sender_type' => 'user',
				'message'     => $message,
			)
		);

		if ( false === $message_id ) {
			return new WP_Error( 'ss_db_error', 'خطا در ثبت پیام.', array( 'status' => 500 ) );
		}

		// تغییر وضعیت تیکت به «پاسخ جدید کاربر» تا در پنل ادمین به‌طور مجزا از یک تیکت تازه‌ثبت‌شده مشخص شود
		if ( 'closed' !== $ticket->status ) {
			SS_Database::update_ticket_status( $ticket_id, 'user_replied' );
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'message_id' => $message_id,
			)
		);
	}

	/**
	 * تبدیل کد وضعیت داخلی تیکت به برچسب فارسی قابل نمایش.
	 *
	 * @param string $status وضعیت داخلی (open, pending, user_replied, resolved, closed).
	 * @return string برچسب فارسی متناظر.
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'open'          => 'در حال بررسی',
			'pending'       => 'در حال بررسی',
			'user_replied'  => 'پاسخ جدید کاربر',
			'resolved'      => 'پاسخ داده شده',
			'closed'        => 'بسته شده',
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : 'در حال بررسی';
	}

	/* =====================================================================
	 * پیاده‌سازی آپلود فایل مشترک
	 * ===================================================================== */

	/**
	 * آپلود مستقل فایل (برای استفاده در چت زنده جهت ضمیمه پیوست به پیام).
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upload_file( WP_REST_Request $request ) {

		if ( empty( $_FILES['file'] ) ) {
			return new WP_Error( 'ss_no_file', 'هیچ فایلی ارسال نشده است.', array( 'status' => 400 ) );
		}

		$upload_result = SS_File_Handler::handle_upload( $_FILES['file'] );

		if ( is_wp_error( $upload_result ) ) {
			return $upload_result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'url'     => $upload_result['url'],
			)
		);
	}

	/* =====================================================================
	 * پیاده‌سازی متدهای اختصاصی پنل مدیریت - چت زنده
	 * ===================================================================== */

	/**
	 * دریافت لیست تمام مکالمات جهت ستون راست پنل ادمین به همراه آخرین پیام.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response
	 */
	public function admin_get_conversations( WP_REST_Request $request ) {
		$unread_only = (bool) $request->get_param( 'unread_only' );

		$conversations = SS_Database::get_all_conversations( $unread_only );

		$formatted = array();

		if ( ! empty( $conversations ) ) {
			foreach ( $conversations as $conv ) {
				$display_name = $conv->guest_name;

				if ( '' === trim( (string) $display_name ) ) {
					if ( $conv->user_id > 0 ) {
						$user_data    = get_userdata( $conv->user_id );
						$display_name = $user_data ? $user_data->display_name : 'کاربر سایت';
					} else {
						$display_name = 'مهمان #' . $conv->id;
					}
				}

				$formatted[] = array(
					'id'               => (int) $conv->id,
					'name'             => $display_name,
					'initial'          => function_exists( 'mb_substr' ) ? mb_substr( $display_name, 0, 1 ) : substr( $display_name, 0, 1 ),
					'last_message'     => $conv->last_message ? $conv->last_message : '',
					'last_message_at'  => $conv->last_message_at ? $conv->last_message_at : $conv->created_at,
					'unread_count'     => (int) $conv->unread_count,
					'status'           => $conv->status,
				);
			}
		}

		return rest_ensure_response(
			array(
				'success'       => true,
				'conversations' => $formatted,
			)
		);
	}

	/**
	 * دریافت پیام‌های یک مکالمه در پنل ادمین و علامت‌گذاری پیام‌های کاربر به عنوان خوانده‌شده.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_get_conversation_messages( WP_REST_Request $request ) {
		$conversation_id = $request->get_param( 'id' );
		$after_id        = $request->get_param( 'after_id' );

		$conversation = SS_Database::get_conversation_by_id( $conversation_id );

		if ( ! $conversation ) {
			return new WP_Error( 'ss_not_found', 'مکالمه یافت نشد.', array( 'status' => 404 ) );
		}

		$messages = SS_Database::get_messages( $conversation_id, $after_id );

		// علامت‌گذاری پیام‌های کاربر به عنوان خوانده‌شده در لحظه باز شدن پنجره گفتگو در پنل ادمین
		SS_Database::mark_conversation_read( $conversation_id );

		return rest_ensure_response(
			array(
				'success'  => true,
				'status'   => $conversation->status,
				'messages' => $this->format_messages( $messages ),
			)
		);
	}

	/**
	 * ارسال پاسخ ادمین در یک مکالمه چت زنده.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_reply_conversation( WP_REST_Request $request ) {
		$conversation_id = $request->get_param( 'id' );
		$message         = $request->get_param( 'message' );

		if ( '' === trim( $message ) ) {
			return new WP_Error( 'ss_empty_message', 'متن پاسخ نمی‌تواند خالی باشد.', array( 'status' => 400 ) );
		}

		$conversation = SS_Database::get_conversation_by_id( $conversation_id );

		if ( ! $conversation ) {
			return new WP_Error( 'ss_not_found', 'مکالمه یافت نشد.', array( 'status' => 404 ) );
		}

		$message_id = SS_Database::insert_admin_reply( $conversation_id, $message );

		if ( false === $message_id ) {
			return new WP_Error( 'ss_db_error', 'خطا در ثبت پاسخ.', array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'message_id' => $message_id,
			)
		);
	}

	/**
	 * خلاصه‌سازی ساده و تزریق تاریخچه یک مکالمه به نالج گراف.
	 *
	 * توجه: در فاز فعلی، خلاصه‌سازی به صورت ساده (اتصال پیام‌های کاربر) انجام
	 * می‌شود. اتصال به مدل هوش مصنوعی برای خلاصه‌سازی واقعی در فاز بعدی
	 * پروژه (زیرمنوی تنظیمات AI) اضافه خواهد شد.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_add_conversation_to_knowledge( WP_REST_Request $request ) {
		$conversation_id = $request->get_param( 'id' );

		$conversation = SS_Database::get_conversation_by_id( $conversation_id );

		if ( ! $conversation ) {
			return new WP_Error( 'ss_not_found', 'مکالمه یافت نشد.', array( 'status' => 404 ) );
		}

		$messages = SS_Database::get_messages( $conversation_id );

		$user_texts = array();

		if ( ! empty( $messages ) ) {
			foreach ( $messages as $msg ) {
				if ( 'user' === $msg->sender_type ) {
					$user_texts[] = $msg->message;
				}
			}
		}

		$summary_text = implode( ' | ', $user_texts );

		$display_name = $conversation->guest_name ? $conversation->guest_name : ( 'مهمان #' . $conversation->id );

		$knowledge_id = SS_Database::save_knowledge(
			array(
				'title'   => 'خلاصه گفتگو با ' . $display_name,
				'content' => $summary_text,
				'tags'    => 'چت زنده، خودکار',
			)
		);

		if ( false === $knowledge_id ) {
			return new WP_Error( 'ss_db_error', 'خطا در افزودن به نالج گراف.', array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success'      => true,
				'knowledge_id' => $knowledge_id,
			)
		);
	}

	/**
	 * دریافت آمار لحظه‌ای مکالمات جهت باکس‌های آمار بالای ستون راست.
	 *
	 * @return WP_REST_Response
	 */
	public function admin_get_stats() {
		$stats = SS_Database::get_conversation_stats();

		return rest_ensure_response(
			array(
				'success' => true,
				'stats'   => $stats,
			)
		);
	}

	/* =====================================================================
	 * پیاده‌سازی متدهای اختصاصی پنل مدیریت - تیکت‌ها
	 * ===================================================================== */

	/**
	 * دریافت لیست تیکت‌ها با قابلیت فیلتر بر اساس واحد، وضعیت و جستجو.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response
	 */
	public function admin_get_tickets( WP_REST_Request $request ) {
		$department = $request->get_param( 'department' );
		$status     = $request->get_param( 'status' );
		$search     = $request->get_param( 'search' );

		$tickets = SS_Database::get_all_tickets( $department, $status, $search );

		$formatted = array();

		if ( ! empty( $tickets ) ) {
			foreach ( $tickets as $ticket ) {
				$formatted[] = array(
					'id'            => (int) $ticket->id,
					'code'          => $ticket->tracking_code,
					'name'          => trim( $ticket->first_name . ' ' . $ticket->last_name ),
					'title'         => $ticket->subject,
					'unit'          => $this->get_department_label( $ticket->department ),
					'status'        => $this->get_status_label( $ticket->status ),
					'status_key'    => $ticket->status,
					'date'          => $ticket->created_at,
				);
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'tickets' => $formatted,
			)
		);
	}

	/**
	 * تبدیل شناسه عددی واحد تیکت به نام فارسی قابل نمایش.
	 *
	 * نام واحد از تنظیمات داینامیک (قابل مدیریت در پنل ادمین) خوانده
	 * می‌شود، نه از یک نگاشت هاردکد. اگر واحدی با این شناسه دیگر وجود
	 * نداشته باشد (مثلاً حذف شده)، خود مقدار خام بازگردانده می‌شود.
	 *
	 * @param string|int $department شناسه عددی واحد ذخیره‌شده در تیکت.
	 * @return string
	 */
	private function get_department_label( $department ) {
		if ( '' === (string) $department ) {
			return '';
		}

		$name = SS_Settings::get_department_name( $department );

		return '' !== $name ? $name : (string) $department;
	}

	/**
	 * دریافت جزئیات کامل یک تیکت جهت نمایش در پنجره گفتگوی پنل ادمین.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_get_ticket_detail( WP_REST_Request $request ) {
		$ticket_id = $request->get_param( 'id' );

		$ticket = SS_Database::get_ticket_by_id( $ticket_id );

		if ( ! $ticket ) {
			return new WP_Error( 'ss_not_found', 'تیکت یافت نشد.', array( 'status' => 404 ) );
		}

		$messages = SS_Database::get_ticket_messages( $ticket_id );

		$formatted_messages = array();

		if ( ! empty( $messages ) ) {
			foreach ( $messages as $msg ) {
				$formatted_messages[] = array(
					'sender_type'    => $msg->sender_type,
					'message'        => $msg->message,
					'attachment_url' => $msg->attachment_url,
					'created_at'     => $msg->created_at,
				);
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'ticket'  => array(
					'id'            => (int) $ticket->id,
					'code'          => $ticket->tracking_code,
					'name'          => trim( $ticket->first_name . ' ' . $ticket->last_name ),
					'title'         => $ticket->subject,
					'unit'          => $this->get_department_label( $ticket->department ),
					'status'        => $this->get_status_label( $ticket->status ),
					'status_key'    => $ticket->status,
					'date'          => $ticket->created_at,
				),
				'messages' => $formatted_messages,
			)
		);
	}

	/**
	 * ارسال پاسخ ادمین در یک تیکت.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_reply_ticket( WP_REST_Request $request ) {
		$ticket_id = $request->get_param( 'id' );
		$message   = $request->get_param( 'message' );

		if ( '' === trim( $message ) ) {
			return new WP_Error( 'ss_empty_message', 'متن پاسخ نمی‌تواند خالی باشد.', array( 'status' => 400 ) );
		}

		$ticket = SS_Database::get_ticket_by_id( $ticket_id );

		if ( ! $ticket ) {
			return new WP_Error( 'ss_not_found', 'تیکت یافت نشد.', array( 'status' => 404 ) );
		}

		$message_id = SS_Database::insert_admin_ticket_reply( $ticket_id, $message );

		if ( false === $message_id ) {
			return new WP_Error( 'ss_db_error', 'خطا در ثبت پاسخ.', array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'message_id' => $message_id,
			)
		);
	}

	/**
	 * خلاصه‌سازی و تزریق تاریخچه یک تیکت به نالج گراف.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_add_ticket_to_knowledge( WP_REST_Request $request ) {
		$ticket_id = $request->get_param( 'id' );

		$ticket = SS_Database::get_ticket_by_id( $ticket_id );

		if ( ! $ticket ) {
			return new WP_Error( 'ss_not_found', 'تیکت یافت نشد.', array( 'status' => 404 ) );
		}

		$messages = SS_Database::get_ticket_messages( $ticket_id );

		$user_texts = array();

		if ( ! empty( $messages ) ) {
			foreach ( $messages as $msg ) {
				if ( 'user' === $msg->sender_type ) {
					$user_texts[] = $msg->message;
				}
			}
		}

		$summary_text = implode( ' | ', $user_texts );

		$knowledge_id = SS_Database::save_knowledge(
			array(
				'title'   => 'خلاصه تیکت: ' . $ticket->subject,
				'content' => $summary_text,
				'tags'    => 'تیکت، خودکار، ' . $this->get_department_label( $ticket->department ),
			)
		);

		if ( false === $knowledge_id ) {
			return new WP_Error( 'ss_db_error', 'خطا در افزودن به نالج گراف.', array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success'      => true,
				'knowledge_id' => $knowledge_id,
			)
		);
	}

	/* =====================================================================
	 * پیاده‌سازی متدهای CRUD نالج گراف
	 * ===================================================================== */

	/**
	 * دریافت لیست کامل گره‌های نالج گراف.
	 *
	 * @return WP_REST_Response
	 */
	public function admin_get_knowledge_list() {
		$items = SS_Database::get_all_knowledge();

		$formatted = array();

		if ( ! empty( $items ) ) {
			foreach ( $items as $item ) {
				$tags = array_filter( array_map( 'trim', explode( '،', str_replace( ',', '،', $item->tags ) ) ) );

				$formatted[] = array(
					'id'      => (int) $item->id,
					'title'   => $item->title,
					'content' => $item->content,
					'tags'    => array_values( $tags ),
				);
			}
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'knowledge' => $formatted,
			)
		);
	}

	/**
	 * ثبت یا به‌روزرسانی یک گره دانش (بسته به وجود پارامتر id در مسیر).
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_save_knowledge( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );

		$data = array(
			'title'   => $request->get_param( 'title' ),
			'content' => $request->get_param( 'content' ),
			'tags'    => $request->get_param( 'tags' ),
		);

		$result = SS_Database::save_knowledge( $data, $id );

		if ( false === $result ) {
			return new WP_Error( 'ss_db_error', 'خطا در ذخیره گره دانش.', array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $result,
			)
		);
	}

	/**
	 * حذف یک گره دانش.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response
	 */
	public function admin_delete_knowledge( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );

		SS_Database::delete_knowledge( $id );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/* =====================================================================
	 * پیاده‌سازی متدهای CRUD سوالات متداول (FAQs)
	 * ===================================================================== */

	/**
	 * دریافت لیست کامل سوالات متداول.
	 *
	 * @return WP_REST_Response
	 */
	public function admin_get_faqs_list() {
		$items = SS_Database::get_all_faqs();

		$formatted = array();

		if ( ! empty( $items ) ) {
			foreach ( $items as $item ) {
				$formatted[] = array(
					'id'       => (int) $item->id,
					'question' => $item->question,
					'answer'   => $item->answer,
					'status'   => 'active' === $item->status ? 'فعال' : 'غیرفعال',
					'status_key' => $item->status,
				);
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'faqs'    => $formatted,
			)
		);
	}

	/**
	 * ثبت یا به‌روزرسانی یک سوال متداول.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_save_faq( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );

		$data = array(
			'question' => $request->get_param( 'question' ),
			'answer'   => $request->get_param( 'answer' ),
			'status'   => $request->get_param( 'status' ),
		);

		$result = SS_Database::save_faq( $data, $id );

		if ( false === $result ) {
			return new WP_Error( 'ss_db_error', 'خطا در ذخیره سوال متداول.', array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $result,
			)
		);
	}

	/**
	 * حذف یک سوال متداول.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response
	 */
	public function admin_delete_faq( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );

		SS_Database::delete_faq( $id );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/* =====================================================================
	 * پیاده‌سازی متدهای CRUD پیام‌های آماده (Canned Responses)
	 * ===================================================================== */

	/**
	 * دریافت لیست کامل پیام‌های آماده.
	 *
	 * @return WP_REST_Response
	 */
	public function admin_get_canned_list() {
		$items = SS_Database::get_all_canned_responses();

		$formatted = array();

		if ( ! empty( $items ) ) {
			foreach ( $items as $item ) {
				$formatted[] = array(
					'id'       => (int) $item->id,
					'title'    => $item->title,
					'text'     => $item->message,
					'category' => $item->category,
				);
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'canned'  => $formatted,
			)
		);
	}

	/**
	 * ثبت یا به‌روزرسانی یک پیام آماده.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response|WP_Error
	 */
	public function admin_save_canned( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );

		$data = array(
			'title'    => $request->get_param( 'title' ),
			'message'  => $request->get_param( 'message' ),
			'category' => $request->get_param( 'category' ),
		);

		$result = SS_Database::save_canned_response( $data, $id );

		if ( false === $result ) {
			return new WP_Error( 'ss_db_error', 'خطا در ذخیره پیام آماده.', array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'id'      => $result,
			)
		);
	}

	/**
	 * حذف یک پیام آماده.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response
	 */
	public function admin_delete_canned( WP_REST_Request $request ) {
		$id = $request->get_param( 'id' );

		SS_Database::delete_canned_response( $id );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/* =====================================================================
	 * پیاده‌سازی متدهای تنظیمات پیشرفته
	 * ===================================================================== */

	/**
	 * دریافت هر سه دسته تنظیمات (ظاهری، هوش مصنوعی، منطق پاسخ‌دهی) به همراه لیست واحدهای تیکت در یک درخواست.
	 *
	 * @return WP_REST_Response
	 */
	public function admin_get_settings() {
		return rest_ensure_response(
			array(
				'success'     => true,
				'ui'          => SS_Settings::get_ui_settings(),
				'ai'          => SS_Settings::get_ai_settings(),
				'logic'       => SS_Settings::get_logic_settings(),
				'departments' => SS_Settings::get_departments(),
			)
		);
	}

	/**
	 * دریافت لیست واحدهای تیکت جهت نمایش در دراپ‌داون فرم ثبت تیکت (سمت فرانت عمومی).
	 *
	 * @return WP_REST_Response
	 */
	public function get_departments_public() {
		return rest_ensure_response(
			array(
				'success'     => true,
				'departments' => SS_Settings::get_departments(),
			)
		);
	}

	/**
	 * ذخیره لیست کامل واحدهای تیکت (افزودن/ویرایش/حذف/ترتیب) از پنل ادمین.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response
	 */
	public function admin_save_departments( WP_REST_Request $request ) {
		$data = $request->get_json_params();

		$departments = SS_Settings::save_departments( isset( $data['departments'] ) ? $data['departments'] : array() );

		return rest_ensure_response(
			array(
				'success'     => true,
				'departments' => $departments,
			)
		);
	}

	/**
	 * ذخیره تنظیمات ظاهری فرانت.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response
	 */
	public function admin_save_settings_ui( WP_REST_Request $request ) {
		SS_Settings::save_ui_settings( $request->get_json_params() );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * ذخیره تنظیمات هوش مصنوعی. آدرس Base URL همیشه ثابت باقی می‌ماند.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response
	 */
	public function admin_save_settings_ai( WP_REST_Request $request ) {
		SS_Settings::save_ai_settings( $request->get_json_params() );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * ذخیره تنظیمات منطق پاسخ‌دهی و رفتار سیستم.
	 *
	 * @param WP_REST_Request $request شیء درخواست REST.
	 * @return WP_REST_Response
	 */
	public function admin_save_settings_logic( WP_REST_Request $request ) {
		SS_Settings::save_logic_settings( $request->get_json_params() );

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * تست زنده اتصال به سرویس هوش مصنوعی gapgpt با استفاده از کلید و مدل ذخیره‌شده.
	 *
	 * از wp_remote_post استاندارد وردپرس استفاده می‌شود (نه cURL مستقیم) تا
	 * سازگاری کامل با محیط هاست وردپرس حفظ شود.
	 *
	 * @return WP_REST_Response
	 */
	public function admin_test_ai_connection() {
		$ai_settings = SS_Settings::get_ai_settings();

		if ( empty( $ai_settings['api_key'] ) || empty( $ai_settings['model'] ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => 'لطفاً ابتدا کلید API و نام مدل را وارد و ذخیره کنید.',
				)
			);
		}

		$response = wp_remote_post(
			trailingslashit( $ai_settings['base_url'] ) . 'chat/completions',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $ai_settings['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'      => $ai_settings['model'],
						'messages'   => array(
							array(
								'role'    => 'user',
								'content' => 'test',
							),
						),
						'max_tokens' => 5,
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => 'خطا در برقراری اتصال: ' . $response->get_error_message(),
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 200 && $status_code < 300 ) {
			return rest_ensure_response(
				array(
					'success' => true,
					'message' => 'اتصال با موفقیت برقرار شد.',
				)
			);
		}

		return rest_ensure_response(
			array(
				'success' => false,
				'message' => 'اتصال ناموفق بود (کد ' . $status_code . '). کلید API یا نام مدل را بررسی کنید.',
			)
		);
	}
}

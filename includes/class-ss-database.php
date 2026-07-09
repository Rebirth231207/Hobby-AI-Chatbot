<?php
/**
 * کلاس مدیریت دیتابیس افزونه پشتیبانی هوشمند.
 *
 * این کلاس تمام کوئری‌های خواندن و نوشتن روی جداول اختصاصی افزونه را
 * در خود جای داده تا منطق دیتابیس از منطق REST API و نمایش کاملاً
 * جدا (Separation of Concerns) باشد. تمام کوئری‌ها با $wpdb->prepare
 * محافظت شده‌اند تا از SQL Injection جلوگیری شود.
 *
 * @package SmartSupport
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SS_Database {

	/**
	 * نام کامل جدول مکالمات چت (شامل اطلاعات کاربر و وضعیت مکالمه).
	 *
	 * @return string
	 */
	public static function table_conversations() {
		global $wpdb;
		return $wpdb->prefix . SS_DB_PREFIX . 'conversations';
	}

	/**
	 * نام کامل جدول پیام‌های چت.
	 *
	 * @return string
	 */
	public static function table_messages() {
		global $wpdb;
		return $wpdb->prefix . SS_DB_PREFIX . 'messages';
	}

	/**
	 * نام کامل جدول تیکت‌ها.
	 *
	 * @return string
	 */
	public static function table_tickets() {
		global $wpdb;
		return $wpdb->prefix . SS_DB_PREFIX . 'tickets';
	}

	/**
	 * نام کامل جدول پیام‌های تیکت (تاریخچه گفتگوی هر تیکت).
	 *
	 * @return string
	 */
	public static function table_ticket_messages() {
		global $wpdb;
		return $wpdb->prefix . SS_DB_PREFIX . 'ticket_messages';
	}

	/**
	 * نام کامل جدول فایل‌های آپلودشده (جهت مدیریت و پاکسازی خودکار).
	 *
	 * @return string
	 */
	public static function table_attachments() {
		global $wpdb;
		return $wpdb->prefix . SS_DB_PREFIX . 'attachments';
	}

	/**
	 * نام کامل جدول نالج گراف (گره‌های دانش هوش مصنوعی).
	 *
	 * @return string
	 */
	public static function table_knowledge() {
		global $wpdb;
		return $wpdb->prefix . SS_DB_PREFIX . 'knowledge';
	}

	/**
	 * نام کامل جدول سوالات متداول (FAQ).
	 *
	 * @return string
	 */
	public static function table_faqs() {
		global $wpdb;
		return $wpdb->prefix . SS_DB_PREFIX . 'faqs';
	}

	/**
	 * نام کامل جدول پیام‌های آماده (Canned Responses).
	 *
	 * @return string
	 */
	public static function table_canned_responses() {
		global $wpdb;
		return $wpdb->prefix . SS_DB_PREFIX . 'canned_responses';
	}

	/* =====================================================================
	 * بخش مکالمات چت زنده (Conversations)
	 * ===================================================================== */

	/**
	 * دریافت یک مکالمه بر اساس شناسه یکتای گفتگو (guest_token یا شناسه عددی).
	 *
	 * @param string $conversation_uid شناسه یکتای مکالمه (UUID رشته‌ای).
	 * @return object|null شیء مکالمه یا null در صورت عدم وجود.
	 */
	public static function get_conversation_by_uid( $conversation_uid ) {
		global $wpdb;
		$table = self::table_conversations();

		// کوئری امن با prepare جهت جلوگیری از تزریق SQL
		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE conversation_uid = %s LIMIT 1",
			$conversation_uid
		);

		return $wpdb->get_row( $sql );
	}

	/**
	 * ساخت یک مکالمه جدید در دیتابیس.
	 *
	 * @param array $data آرایه شامل conversation_uid, user_id, guest_name, guest_phone.
	 * @return int|false شناسه عددی رکورد جدید یا false در صورت خطا.
	 */
	public static function create_conversation( $data ) {
		global $wpdb;
		$table = self::table_conversations();

		$inserted = $wpdb->insert(
			$table,
			array(
				'conversation_uid' => $data['conversation_uid'],
				'user_id'          => isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0,
				'guest_name'       => isset( $data['guest_name'] ) ? $data['guest_name'] : '',
				'guest_phone'      => isset( $data['guest_phone'] ) ? $data['guest_phone'] : '',
				'status'           => 'unanswered', // وضعیت اولیه: بی‌پاسخ
				'created_at'       => current_time( 'mysql' ),
				'updated_at'       => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * به‌روزرسانی وضعیت یک مکالمه (مثلاً: unanswered, answered, closed).
	 *
	 * @param int    $conversation_id شناسه عددی مکالمه.
	 * @param string $status         وضعیت جدید.
	 * @return int|false تعداد ردیف‌های تغییر یافته یا false در خطا.
	 */
	public static function update_conversation_status( $conversation_id, $status ) {
		global $wpdb;
		$table = self::table_conversations();

		return $wpdb->update(
			$table,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $conversation_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * ثبت زمان آخرین بروزرسانی مکالمه (هر بار پیام جدیدی رد و بدل می‌شود).
	 *
	 * @param int $conversation_id شناسه عددی مکالمه.
	 * @return void
	 */
	public static function touch_conversation( $conversation_id ) {
		global $wpdb;
		$table = self::table_conversations();

		$wpdb->update(
			$table,
			array( 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => absint( $conversation_id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/* =====================================================================
	 * بخش پیام‌های چت زنده (Messages)
	 * ===================================================================== */

	/**
	 * ثبت یک پیام جدید در یک مکالمه.
	 *
	 * @param array $data آرایه شامل conversation_id, sender_type, message, attachment_id, reply_to.
	 * @return int|false شناسه پیام جدید یا false در خطا.
	 */
	public static function insert_message( $data ) {
		global $wpdb;
		$table = self::table_messages();

		$inserted = $wpdb->insert(
			$table,
			array(
				'conversation_id' => absint( $data['conversation_id'] ),
				'sender_type'     => $data['sender_type'], // 'user' یا 'admin' یا 'ai'
				'message'         => $data['message'],
				'attachment_url'  => isset( $data['attachment_url'] ) ? $data['attachment_url'] : '',
				'reply_to_text'   => isset( $data['reply_to_text'] ) ? $data['reply_to_text'] : '',
				'is_read'         => isset( $data['is_read'] ) ? absint( $data['is_read'] ) : 0,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * دریافت تمام پیام‌های یک مکالمه بر اساس شناسه، مرتب‌شده بر اساس زمان.
	 *
	 * @param int $conversation_id شناسه عددی مکالمه.
	 * @param int $after_id       فقط پیام‌های بعد از این شناسه (برای پولینگ زنده). صفر یعنی همه.
	 * @return array آرایه‌ای از اشیاء پیام.
	 */
	public static function get_messages( $conversation_id, $after_id = 0 ) {
		global $wpdb;
		$table = self::table_messages();

		if ( $after_id > 0 ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE conversation_id = %d AND id > %d ORDER BY id ASC",
				absint( $conversation_id ),
				absint( $after_id )
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY id ASC",
				absint( $conversation_id )
			);
		}

		return $wpdb->get_results( $sql );
	}

	/* =====================================================================
	 * بخش تیکت‌ها (Tickets)
	 * ===================================================================== */

	/**
	 * ساخت کد پیگیری یکتا برای تیکت با پیشوند HBT.
	 *
	 * @return string کد پیگیری یکتا مانند HBT-48213.
	 */
	public static function generate_tracking_code() {
		global $wpdb;
		$table = self::table_tickets();

		do {
			// تولید یک عدد تصادفی ۵ رقمی برای کد پیگیری
			$code = 'HBT-' . wp_rand( 10000, 99999 );

			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(id) FROM {$table} WHERE tracking_code = %s",
					$code
				)
			);
		} while ( $exists > 0 );

		return $code;
	}

	/**
	 * ثبت تیکت جدید در دیتابیس.
	 *
	 * @param array $data آرایه شامل تمام فیلدهای تیکت.
	 * @return array|false آرایه شامل id و tracking_code، یا false در خطا.
	 */
	public static function create_ticket( $data ) {
		global $wpdb;
		$table = self::table_tickets();

		$tracking_code = self::generate_tracking_code();

		$inserted = $wpdb->insert(
			$table,
			array(
				'tracking_code'  => $tracking_code,
				'user_id'        => isset( $data['user_id'] ) ? absint( $data['user_id'] ) : 0,
				'first_name'     => $data['first_name'],
				'last_name'      => $data['last_name'],
				'phone'          => $data['phone'],
				'subject'        => $data['subject'],
				'department'     => $data['department'],
				'status'         => 'open', // وضعیت اولیه: باز
				'created_at'     => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		$ticket_id = (int) $wpdb->insert_id;

		// ثبت پیام اولیه تیکت (متن اصلی درخواست) در جدول پیام‌های تیکت
		self::insert_ticket_message(
			array(
				'ticket_id'      => $ticket_id,
				'sender_type'    => 'user',
				'message'        => $data['message'],
				'attachment_url' => isset( $data['attachment_url'] ) ? $data['attachment_url'] : '',
			)
		);

		return array(
			'id'            => $ticket_id,
			'tracking_code' => $tracking_code,
		);
	}

	/**
	 * جستجوی تیکت‌ها بر اساس شماره تماس (برای بخش پیگیری تیکت).
	 *
	 * @param string $phone شماره تماس ثبت‌شده.
	 * @return array آرایه‌ای از اشیاء تیکت.
	 */
	public static function get_tickets_by_phone( $phone ) {
		global $wpdb;
		$table = self::table_tickets();

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE phone = %s ORDER BY created_at DESC LIMIT 20",
			$phone
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * دریافت یک تیکت بر اساس کد پیگیری.
	 *
	 * @param string $tracking_code کد پیگیری تیکت.
	 * @return object|null شیء تیکت یا null.
	 */
	public static function get_ticket_by_tracking_code( $tracking_code ) {
		global $wpdb;
		$table = self::table_tickets();

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE tracking_code = %s LIMIT 1",
			$tracking_code
		);

		return $wpdb->get_row( $sql );
	}

	/**
	 * دریافت یک تیکت بر اساس شناسه عددی به همراه بررسی مالکیت شماره تماس.
	 *
	 * این تابع برای جلوگیری از دسترسی غیرمجاز به تیکت دیگران استفاده می‌شود؛
	 * کاربر فقط با ارائه شماره تماس صحیح می‌تواند جزئیات تیکت را ببیند.
	 *
	 * @param int    $ticket_id شناسه عددی تیکت.
	 * @param string $phone     شماره تماس جهت تطبیق مالکیت.
	 * @return object|null شیء تیکت یا null در صورت عدم تطابق.
	 */
	public static function get_ticket_by_id_and_phone( $ticket_id, $phone ) {
		global $wpdb;
		$table = self::table_tickets();

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d AND phone = %s LIMIT 1",
			absint( $ticket_id ),
			$phone
		);

		return $wpdb->get_row( $sql );
	}

	/**
	 * به‌روزرسانی وضعیت یک تیکت.
	 *
	 * @param int    $ticket_id شناسه عددی تیکت.
	 * @param string $status   وضعیت جدید (open, pending, resolved, closed).
	 * @return int|false
	 */
	public static function update_ticket_status( $ticket_id, $status ) {
		global $wpdb;
		$table = self::table_tickets();

		return $wpdb->update(
			$table,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $ticket_id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/* =====================================================================
	 * بخش پیام‌های تیکت (Ticket Messages)
	 * ===================================================================== */

	/**
	 * ثبت یک پیام جدید در تاریخچه یک تیکت.
	 *
	 * @param array $data آرایه شامل ticket_id, sender_type, message, attachment_url.
	 * @return int|false شناسه پیام جدید یا false.
	 */
	public static function insert_ticket_message( $data ) {
		global $wpdb;
		$table = self::table_ticket_messages();

		$inserted = $wpdb->insert(
			$table,
			array(
				'ticket_id'      => absint( $data['ticket_id'] ),
				'sender_type'    => $data['sender_type'],
				'message'        => $data['message'],
				'attachment_url' => isset( $data['attachment_url'] ) ? $data['attachment_url'] : '',
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * دریافت تمام پیام‌های یک تیکت بر اساس شناسه.
	 *
	 * @param int $ticket_id شناسه عددی تیکت.
	 * @return array آرایه‌ای از اشیاء پیام.
	 */
	public static function get_ticket_messages( $ticket_id ) {
		global $wpdb;
		$table = self::table_ticket_messages();

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE ticket_id = %d ORDER BY id ASC",
			absint( $ticket_id )
		);

		return $wpdb->get_results( $sql );
	}

	/* =====================================================================
	 * بخش فایل‌های ضمیمه (Attachments) - جهت مدیریت پاکسازی خودکار
	 * ===================================================================== */

	/**
	 * ثبت یک فایل آپلودشده در جدول مدیریت فایل‌ها.
	 *
	 * @param string $file_path مسیر مطلق فایل روی سرور.
	 * @param string $file_url  آدرس URL عمومی فایل.
	 * @return int|false شناسه رکورد جدید یا false.
	 */
	public static function register_attachment( $file_path, $file_url ) {
		global $wpdb;
		$table = self::table_attachments();

		$inserted = $wpdb->insert(
			$table,
			array(
				'file_path'  => $file_path,
				'file_url'   => $file_url,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * دریافت لیست فایل‌های قدیمی‌تر از تعداد روز مشخص جهت پاکسازی خودکار.
	 *
	 * @param int $days تعداد روزهایی که باید از تاریخ آپلود گذشته باشد.
	 * @return array آرایه‌ای از اشیاء فایل.
	 */
	public static function get_old_attachments( $days ) {
		global $wpdb;
		$table = self::table_attachments();

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY)",
			current_time( 'mysql' ),
			absint( $days )
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * حذف رکورد یک فایل از جدول مدیریت پس از پاکسازی موفق.
	 *
	 * @param int $attachment_id شناسه رکورد فایل.
	 * @return int|false
	 */
	public static function delete_attachment_record( $attachment_id ) {
		global $wpdb;
		$table = self::table_attachments();

		return $wpdb->delete(
			$table,
			array( 'id' => absint( $attachment_id ) ),
			array( '%d' )
		);
	}

	/* =====================================================================
	 * آمار لحظه‌ای برای داشبورد ادمین (فاز بعدی UI ادمین از این متد استفاده می‌کند)
	 * ===================================================================== */

	/**
	 * دریافت تعداد مکالمات به تفکیک وضعیت (بی‌پاسخ، پاسخ‌داده‌شده، بسته‌شده).
	 *
	 * @return array آرایه شامل کلیدهای unanswered, answered, closed.
	 */
	public static function get_conversation_stats() {
		global $wpdb;
		$table = self::table_conversations();

		$results = $wpdb->get_results(
			"SELECT status, COUNT(id) as total FROM {$table} GROUP BY status",
			ARRAY_A
		);

		$stats = array(
			'unanswered' => 0,
			'answered'   => 0,
			'closed'     => 0,
		);

		if ( ! empty( $results ) ) {
			foreach ( $results as $row ) {
				if ( isset( $stats[ $row['status'] ] ) ) {
					$stats[ $row['status'] ] = (int) $row['total'];
				}
			}
		}

		return $stats;
	}

	/* =====================================================================
	 * متدهای اختصاصی پنل مدیریت - چت زنده
	 * ===================================================================== */

	/**
	 * دریافت لیست تمام مکالمات جهت نمایش در ستون راست پنل ادمین.
	 *
	 * لیست بر اساس آخرین بروزرسانی (جدیدترین چت‌ها ابتدا) مرتب می‌شود.
	 * در صورت درخواست فیلتر، فقط مکالمات دارای پیام خوانده‌نشده کاربر
	 * بازگردانده می‌شوند.
	 *
	 * @param bool $only_unread فیلتر فقط مکالمات خوانده‌نشده.
	 * @return array آرایه‌ای از اشیاء مکالمه به همراه آخرین پیام.
	 */
	public static function get_all_conversations( $only_unread = false ) {
		global $wpdb;
		$table_conv = self::table_conversations();
		$table_msg  = self::table_messages();

		$having = $only_unread ? 'HAVING unread_count > 0' : '';

		$sql = "SELECT c.*,
					(SELECT message FROM {$table_msg} m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
					(SELECT created_at FROM {$table_msg} m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message_at,
					(SELECT COUNT(id) FROM {$table_msg} m WHERE m.conversation_id = c.id AND m.sender_type = 'user' AND m.is_read = 0) AS unread_count
				FROM {$table_conv} c
				{$having}
				ORDER BY c.updated_at DESC";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- کوئری فاقد ورودی خارجی است، مقدار $having از لیست ثابت داخلی ساخته می‌شود
		return $wpdb->get_results( $sql );
	}

	/**
	 * دریافت اطلاعات یک مکالمه بر اساس شناسه عددی.
	 *
	 * @param int $conversation_id شناسه عددی مکالمه.
	 * @return object|null
	 */
	public static function get_conversation_by_id( $conversation_id ) {
		global $wpdb;
		$table = self::table_conversations();

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
			absint( $conversation_id )
		);

		return $wpdb->get_row( $sql );
	}

	/**
	 * ثبت پاسخ ادمین در یک مکالمه و به‌روزرسانی وضعیت به «پاسخ‌داده‌شده».
	 *
	 * @param int    $conversation_id شناسه عددی مکالمه.
	 * @param string $message         متن پاسخ ادمین.
	 * @return int|false شناسه پیام جدید یا false.
	 */
	public static function insert_admin_reply( $conversation_id, $message ) {
		$message_id = self::insert_message(
			array(
				'conversation_id' => $conversation_id,
				'sender_type'     => 'admin',
				'message'         => $message,
				'is_read'         => 1,
			)
		);

		if ( false !== $message_id ) {
			self::update_conversation_status( $conversation_id, 'answered' );
		}

		return $message_id;
	}

	/**
	 * علامت‌گذاری تمام پیام‌های کاربر در یک مکالمه به عنوان خوانده‌شده.
	 *
	 * زمانی فراخوانی می‌شود که ادمین یک مکالمه را در پنل باز می‌کند.
	 *
	 * @param int $conversation_id شناسه عددی مکالمه.
	 * @return void
	 */
	public static function mark_conversation_read( $conversation_id ) {
		global $wpdb;
		$table = self::table_messages();

		$wpdb->update(
			$table,
			array( 'is_read' => 1 ),
			array(
				'conversation_id' => absint( $conversation_id ),
				'sender_type'     => 'user',
			),
			array( '%d' ),
			array( '%d', '%s' )
		);
	}

	/* =====================================================================
	 * متدهای اختصاصی پنل مدیریت - تیکت‌ها
	 * ===================================================================== */

	/**
	 * دریافت لیست تمام تیکت‌ها جهت نمایش در جدول گرید پنل ادمین، با قابلیت
	 * فیلتر بر اساس واحد و وضعیت.
	 *
	 * @param string $department فیلتر واحد ('' یعنی همه واحدها).
	 * @param string $status     فیلتر وضعیت ('' یعنی همه وضعیت‌ها).
	 * @param string $search     عبارت جستجو در عنوان یا نام (اختیاری).
	 * @return array آرایه‌ای از اشیاء تیکت.
	 */
	public static function get_all_tickets( $department = '', $status = '', $search = '' ) {
		global $wpdb;
		$table = self::table_tickets();

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $department ) {
			$where[]  = 'department = %s';
			$params[] = $department;
		}

		if ( '' !== $status ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		if ( '' !== $search ) {
			$where[]  = '(subject LIKE %s OR first_name LIKE %s OR last_name LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_clause = implode( ' AND ', $where );
		$sql          = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY created_at DESC";

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * دریافت یک تیکت بر اساس شناسه عددی (بدون نیاز به بررسی شماره تماس - جهت پنل ادمین).
	 *
	 * @param int $ticket_id شناسه عددی تیکت.
	 * @return object|null
	 */
	public static function get_ticket_by_id( $ticket_id ) {
		global $wpdb;
		$table = self::table_tickets();

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
			absint( $ticket_id )
		);

		return $wpdb->get_row( $sql );
	}

	/**
	 * ثبت پاسخ ادمین در تاریخچه یک تیکت و به‌روزرسانی وضعیت.
	 *
	 * @param int    $ticket_id شناسه عددی تیکت.
	 * @param string $message   متن پاسخ ادمین.
	 * @return int|false
	 */
	public static function insert_admin_ticket_reply( $ticket_id, $message ) {
		$message_id = self::insert_ticket_message(
			array(
				'ticket_id'   => $ticket_id,
				'sender_type' => 'admin',
				'message'     => $message,
			)
		);

		if ( false !== $message_id ) {
			self::update_ticket_status( $ticket_id, 'resolved' );
		}

		return $message_id;
	}

	/* =====================================================================
	 * متدهای اختصاصی نالج گراف (Knowledge Graph)
	 * ===================================================================== */

	/**
	 * دریافت تمام گره‌های دانش ثبت‌شده.
	 *
	 * @return array
	 */
	public static function get_all_knowledge() {
		global $wpdb;
		$table = self::table_knowledge();

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
	}

	/**
	 * دریافت یک گره دانش بر اساس شناسه.
	 *
	 * @param int $id شناسه گره دانش.
	 * @return object|null
	 */
	public static function get_knowledge_by_id( $id ) {
		global $wpdb;
		$table = self::table_knowledge();

		$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $id ) );

		return $wpdb->get_row( $sql );
	}

	/**
	 * ثبت یا به‌روزرسانی یک گره دانش (بر اساس وجود یا عدم وجود شناسه).
	 *
	 * @param array    $data آرایه شامل title, content, tags.
	 * @param int|null $id   شناسه گره جهت ویرایش، یا null برای ثبت جدید.
	 * @return int|false شناسه رکورد یا false در خطا.
	 */
	public static function save_knowledge( $data, $id = null ) {
		global $wpdb;
		$table = self::table_knowledge();

		$fields = array(
			'title'      => $data['title'],
			'content'    => $data['content'],
			'tags'       => $data['tags'],
			'updated_at' => current_time( 'mysql' ),
		);
		$formats = array( '%s', '%s', '%s', '%s' );

		if ( $id ) {
			$updated = $wpdb->update( $table, $fields, array( 'id' => absint( $id ) ), $formats, array( '%d' ) );
			return ( false === $updated ) ? false : absint( $id );
		}

		$fields['created_at'] = current_time( 'mysql' );
		$formats[]            = '%s';

		$inserted = $wpdb->insert( $table, $fields, $formats );

		return ( false === $inserted ) ? false : (int) $wpdb->insert_id;
	}

	/**
	 * حذف یک گره دانش بر اساس شناسه.
	 *
	 * @param int $id شناسه گره دانش.
	 * @return int|false
	 */
	public static function delete_knowledge( $id ) {
		global $wpdb;
		$table = self::table_knowledge();

		return $wpdb->delete( $table, array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/**
	 * جستجوی ساده در گره‌های دانش بر اساس کلیدواژه (جهت استفاده لایه AI در فاز بعد).
	 *
	 * @param string $keyword کلیدواژه جستجو.
	 * @return array
	 */
	public static function search_knowledge( $keyword ) {
		global $wpdb;
		$table = self::table_knowledge();
		$like  = '%' . $wpdb->esc_like( $keyword ) . '%';

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE title LIKE %s OR content LIKE %s OR tags LIKE %s ORDER BY id DESC",
			$like,
			$like,
			$like
		);

		return $wpdb->get_results( $sql );
	}

	/* =====================================================================
	 * متدهای اختصاصی سوالات متداول (FAQs)
	 * ===================================================================== */

	/**
	 * دریافت تمام سوالات متداول.
	 *
	 * @param bool $only_active فقط سوالات فعال (جهت نمایش در فرانت).
	 * @return array
	 */
	public static function get_all_faqs( $only_active = false ) {
		global $wpdb;
		$table = self::table_faqs();

		if ( $only_active ) {
			return $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'active' ORDER BY sort_order ASC, id ASC" );
		}

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC, id ASC" );
	}

	/**
	 * دریافت یک سوال متداول بر اساس شناسه.
	 *
	 * @param int $id شناسه سوال.
	 * @return object|null
	 */
	public static function get_faq_by_id( $id ) {
		global $wpdb;
		$table = self::table_faqs();

		$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $id ) );

		return $wpdb->get_row( $sql );
	}

	/**
	 * دریافت یک سوال متداول فعال بر اساس متن دقیق سوال.
	 *
	 * برای پاسخ‌دهی خودکار سمت سرور استفاده می‌شود: وقتی کاربر روی یکی
	 * از دکمه‌های FAQ در فرانت کلیک می‌کند، متن دقیق سوال به این متد
	 * داده می‌شود تا پاسخ متناظر آن (در صورت فعال بودن) پیدا شود.
	 *
	 * @param string $question متن دقیق سوال.
	 * @return object|null
	 */
	public static function get_faq_by_question( $question ) {
		global $wpdb;
		$table = self::table_faqs();

		$sql = $wpdb->prepare(
			"SELECT * FROM {$table} WHERE question = %s AND status = 'active' LIMIT 1",
			$question
		);

		return $wpdb->get_row( $sql );
	}

	/**
	 * ثبت یا به‌روزرسانی یک سوال متداول.
	 *
	 * @param array    $data آرایه شامل question, answer, status.
	 * @param int|null $id   شناسه جهت ویرایش، یا null برای ثبت جدید.
	 * @return int|false
	 */
	public static function save_faq( $data, $id = null ) {
		global $wpdb;
		$table = self::table_faqs();

		$fields = array(
			'question' => $data['question'],
			'answer'   => $data['answer'],
			'status'   => isset( $data['status'] ) ? $data['status'] : 'active',
		);
		$formats = array( '%s', '%s', '%s' );

		if ( $id ) {
			$updated = $wpdb->update( $table, $fields, array( 'id' => absint( $id ) ), $formats, array( '%d' ) );
			return ( false === $updated ) ? false : absint( $id );
		}

		$fields['created_at'] = current_time( 'mysql' );
		$formats[]            = '%s';

		$inserted = $wpdb->insert( $table, $fields, $formats );

		return ( false === $inserted ) ? false : (int) $wpdb->insert_id;
	}

	/**
	 * حذف یک سوال متداول.
	 *
	 * @param int $id شناسه سوال.
	 * @return int|false
	 */
	public static function delete_faq( $id ) {
		global $wpdb;
		$table = self::table_faqs();

		return $wpdb->delete( $table, array( 'id' => absint( $id ) ), array( '%d' ) );
	}

	/* =====================================================================
	 * متدهای اختصاصی پیام‌های آماده (Canned Responses)
	 * ===================================================================== */

	/**
	 * دریافت تمام پیام‌های آماده.
	 *
	 * @return array
	 */
	public static function get_all_canned_responses() {
		global $wpdb;
		$table = self::table_canned_responses();

		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC" );
	}

	/**
	 * دریافت یک پیام آماده بر اساس شناسه.
	 *
	 * @param int $id شناسه پیام آماده.
	 * @return object|null
	 */
	public static function get_canned_response_by_id( $id ) {
		global $wpdb;
		$table = self::table_canned_responses();

		$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", absint( $id ) );

		return $wpdb->get_row( $sql );
	}

	/**
	 * ثبت یا به‌روزرسانی یک پیام آماده.
	 *
	 * @param array    $data آرایه شامل title, message, category.
	 * @param int|null $id   شناسه جهت ویرایش، یا null برای ثبت جدید.
	 * @return int|false
	 */
	public static function save_canned_response( $data, $id = null ) {
		global $wpdb;
		$table = self::table_canned_responses();

		$fields = array(
			'title'    => $data['title'],
			'message'  => $data['message'],
			'category' => $data['category'],
		);
		$formats = array( '%s', '%s', '%s' );

		if ( $id ) {
			$updated = $wpdb->update( $table, $fields, array( 'id' => absint( $id ) ), $formats, array( '%d' ) );
			return ( false === $updated ) ? false : absint( $id );
		}

		$fields['created_at'] = current_time( 'mysql' );
		$formats[]            = '%s';

		$inserted = $wpdb->insert( $table, $fields, $formats );

		return ( false === $inserted ) ? false : (int) $wpdb->insert_id;
	}

	/**
	 * حذف یک پیام آماده.
	 *
	 * @param int $id شناسه پیام آماده.
	 * @return int|false
	 */
	public static function delete_canned_response( $id ) {
		global $wpdb;
		$table = self::table_canned_responses();

		return $wpdb->delete( $table, array( 'id' => absint( $id ) ), array( '%d' ) );
	}
}

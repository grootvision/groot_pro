<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** خواندن CSV یا XLSX → آرایه‌ی سطرها (هر سطر آرایه‌ای از رشته‌ها) */
function gvwr_read_table( $path, $name ) {
	$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	if ( 'xlsx' === $ext ) {
		return gvwr_read_xlsx( $path );
	}
	$raw = file_get_contents( $path );
	if ( false === $raw ) {
		return array();
	}
	$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
	$first = strtok( $raw, "\n" );
	$delim = ',';
	foreach ( array( "\t", ';' ) as $d ) {
		if ( substr_count( (string) $first, $d ) > substr_count( (string) $first, $delim ) ) {
			$delim = $d;
		}
	}
	$rows = array();
	$fh   = fopen( 'php://temp', 'r+' );
	fwrite( $fh, $raw );
	rewind( $fh );
	while ( ( $r = fgetcsv( $fh, 0, $delim ) ) !== false ) {
		if ( array( null ) !== $r ) {
			$rows[] = array_map( 'trim', $r );
		}
	}
	fclose( $fh );
	return $rows;
}

function gvwr_read_xlsx( $path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return array();
	}
	$z = new ZipArchive();
	if ( true !== $z->open( $path ) ) {
		return array();
	}
	$shared = array();
	$ss     = $z->getFromName( 'xl/sharedStrings.xml' );
	if ( $ss ) {
		$x = @simplexml_load_string( $ss );
		if ( $x ) {
			foreach ( $x->si as $si ) {
				$t = '';
				if ( isset( $si->t ) ) {
					$t = (string) $si->t;
				} else {
					foreach ( $si->r as $r ) {
						$t .= (string) $r->t;
					}
				}
				$shared[] = $t;
			}
		}
	}
	$sheet = $z->getFromName( 'xl/worksheets/sheet1.xml' );
	$z->close();
	if ( ! $sheet ) {
		return array();
	}
	$x = @simplexml_load_string( $sheet );
	if ( ! $x ) {
		return array();
	}
	$rows = array();
	foreach ( $x->sheetData->row as $row ) {
		$r = array();
		foreach ( $row->c as $c ) {
			preg_match( '/^([A-Z]+)/', (string) $c['r'], $m );
			$idx = 0;
			foreach ( str_split( isset( $m[1] ) ? $m[1] : 'A' ) as $ch ) {
				$idx = $idx * 26 + ( ord( $ch ) - 64 );
			}
			$idx--;
			$t = (string) $c['t'];
			if ( 's' === $t ) {
				$v = isset( $shared[ (int) $c->v ] ) ? $shared[ (int) $c->v ] : '';
			} elseif ( 'inlineStr' === $t ) {
				$v = (string) $c->is->t;
			} else {
				$v = (string) $c->v;
			}
			$r[ $idx ] = trim( $v );
		}
		if ( $r ) {
			$max = max( array_keys( $r ) );
			$out = array();
			for ( $i = 0; $i <= $max; $i++ ) {
				$out[] = isset( $r[ $i ] ) ? $r[ $i ] : '';
			}
			$rows[] = $out;
		}
	}
	return $rows;
}

function gvwr_col_letter( $i ) {
	$s = '';
	for ( $i++; $i > 0; $i = (int) ( ( $i - 1 ) / 26 ) ) {
		$s = chr( 65 + ( $i - 1 ) % 26 ) . $s;
	}
	return $s;
}

/** ساخت فایل XLSX ساده (راست‌به‌چپ) و ارسال برای دانلود؛ اگر ZipArchive نبود، CSV می‌دهد. */
function gvwr_download_table( $rows, $basename, $format = 'xlsx' ) {
	nocache_headers();
	if ( 'xlsx' === $format && class_exists( 'ZipArchive' ) ) {
		$tmp = wp_tempnam( 'gvwr' );
		$z   = new ZipArchive();
		$z->open( $tmp, ZipArchive::OVERWRITE );
		$z->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
		$z->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$z->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$z->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>' );
		$x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView rightToLeft="1" workbookViewId="0"/></sheetViews><sheetData>';
		foreach ( $rows as $ri => $row ) {
			$x .= '<row r="' . ( $ri + 1 ) . '">';
			foreach ( array_values( $row ) as $ci => $v ) {
				$ref = gvwr_col_letter( $ci ) . ( $ri + 1 );
				if ( is_int( $v ) || is_float( $v ) ) {
					$x .= '<c r="' . $ref . '"><v>' . $v . '</v></c>';
				} else {
					$x .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</t></is></c>';
				}
			}
			$x .= '</row>';
		}
		$x .= '</sheetData></worksheet>';
		$z->addFromString( 'xl/worksheets/sheet1.xml', $x );
		$z->close();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . $basename . '.xlsx"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $basename . '.csv"' );
	echo "\xEF\xBB\xBF"; // برای نمایش درست فارسی در Excel
	$out = fopen( 'php://output', 'w' );
	foreach ( $rows as $r ) {
		fputcsv( $out, $r );
	}
	fclose( $out );
	exit;
}

/** درخواست خروجی (admin-post) */
add_action( 'admin_post_gvwr_export', 'gvwr_handle_export' );
function gvwr_handle_export() {
	check_admin_referer( 'gvwr_export' );
	if ( ! gvwr_can_submit() ) {
		wp_die( 'دسترسی ندارید.' );
	}
	$type   = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';
	$format = ( isset( $_GET['format'] ) && 'csv' === $_GET['format'] ) ? 'csv' : 'xlsx';

	if ( 'keywords_template' === $type ) {
		gvwr_download_table( array( array( 'کلمه کلیدی', 'صفحه هدف (اختیاری)', 'رتبه فعلی (اختیاری)' ), array( 'نمونه کلمه', 'https://example.com/page', 12 ) ), 'keywords-template', $format );
	}

	if ( 'keywords' === $type ) {
		$kws = gvwr_keywords( 0, false );
		$ser = gvwr_rank_series( wp_list_pluck( $kws, 'id' ), '', 2 );
		$out = array( array( 'کلمه کلیدی', 'صفحه هدف', 'رتبه قبلی', 'رتبه فعلی', 'تغییر' ) );
		foreach ( $kws as $k ) {
			$g     = gvwr_growth( isset( $ser[ $k->id ] ) ? $ser[ $k->id ] : array() );
			$out[] = array( $k->keyword, $k->target_url, (string) $g['prev'], (string) $g['last'], $g['delta'] );
		}
		gvwr_download_table( $out, 'keywords-' . gvwr_today(), $format );
	}

	if ( 'items' === $type ) {
		if ( ! gvwr_can_manage() ) {
			wp_die( 'دسترسی ندارید.' );
		}
		$rg = gvwr_range( isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : 'month', isset( $_GET['anchor'] ) ? sanitize_text_field( $_GET['anchor'] ) : '' );
		$f  = array( 'from' => $rg['from'], 'to' => $rg['to'] );
		foreach ( array( 'user_id' => 'user', 'category_id' => 'cat', 'project_id' => 'project' ) as $k => $g ) {
			if ( ! empty( $_GET[ $g ] ) ) {
				$f[ $k ] = (int) $_GET[ $g ];
			}
		}
		$out = array( array( 'تاریخ', 'کارمند', 'دسته‌بندی', 'توضیحات', 'تعداد', 'لینک', 'شروع', 'پایان', 'مدت (دقیقه)', 'وضعیت' ) );
		$st  = array( 'pending' => 'در انتظار', 'approved' => 'تأیید شده', 'rejected' => 'رد شده' );
		foreach ( gvwr_items( $f, 100000, 0, 'ASC' ) as $r ) {
			$out[] = array( gvwr_date( $r->work_date ), $r->display_name, $r->cat_name, $r->description, (int) $r->qty, $r->url, substr( (string) $r->start_time, 0, 5 ), substr( (string) $r->end_time, 0, 5 ), (string) $r->duration_min, $st[ $r->status ] );
		}
		gvwr_download_table( $out, 'work-report-' . $rg['from'] . '_' . $rg['to'], $format );
	}
	wp_die( 'نوع خروجی نامعتبر است.' );
}

function gvwr_export_url( $type, $extra = array() ) {
	return wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'gvwr_export', 'type' => $type ), $extra ), admin_url( 'admin-post.php' ) ), 'gvwr_export' );
}

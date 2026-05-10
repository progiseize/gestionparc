<?php
/* Copyright (C) 2024 Anthony Damhet  <a.damhet@progiseize.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

// avoid timeout for big export
set_time_limit(0);

require_once DOL_DOCUMENT_ROOT . '/core/modules/export/modules_export.php';
include_once DOL_DOCUMENT_ROOT . '/core/modules/export/export_excel2007.modules.php';

use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Class to build Excel export files for GestionParc verification reports.
 */
class GestionParcExport extends ExportExcel2007
{
	// ── Palette ─────────────────────────────────────────────────────────────
	const COLOR_TITLE_BG    = 'FF173652'; // dark blue — title bar
	const COLOR_TITLE_FG    = 'FFFFFFFF'; // white
	const COLOR_INFO_BG     = 'FFFFFFFF'; // white — header info rows (no tint)
	const COLOR_INFO_LABEL  = 'FF173652'; // dark blue — label text
	const COLOR_INFO_VALUE  = 'FF333333'; // dark grey — value text
	const COLOR_LOGO_BG     = 'FFFFFFFF'; // white — logo zone
	const COLOR_COLHDR_BG   = 'FF173652'; // dark blue — column header row
	const COLOR_COLHDR_FG   = 'FFFFFFFF'; // white
	const COLOR_ROW_ODD     = 'FFF5F5F5'; // light grey — alternate data rows
	const COLOR_SUMMARY_BG  = 'FFE8E8E8'; // light grey — summary row
	const COLOR_BORDER_DARK = 'FF173652'; // dark blue — outer borders
	const COLOR_BORDER_SOFT = 'FFD0D0D0'; // grey — inner cell borders

	// ── Column index helper ──────────────────────────────────────────────────

	/**
	 * Convert a 0-based column index to an Excel column letter (A, B, …, Z, AA, …).
	 * Uses PhpSpreadsheet — no 26-column limit.
	 */
	public function col($index0)
	{
		return Coordinate::stringFromColumnIndex($index0 + 1);
	}

	// ── Style helpers ────────────────────────────────────────────────────────

	protected function applyFill($sheet, $range, $argb)
	{
		$sheet->getStyle($range)->getFill()
			->setFillType(Fill::FILL_SOLID)
			->getStartColor()->setARGB($argb);
	}

	protected function applyFontColor($sheet, $range, $argb)
	{
		$sheet->getStyle($range)->getFont()->getColor()->setARGB($argb);
	}

	protected function applyBorderOutline($sheet, $range, $style = Border::BORDER_THIN, $argb = self::COLOR_BORDER_SOFT)
	{
		$sheet->getStyle($range)->getBorders()->getOutline()
			->setBorderStyle($style)->getColor()->setARGB($argb);
	}

	public function applyBorderAll($sheet, $range, $style = Border::BORDER_THIN, $argb = self::COLOR_BORDER_SOFT)
	{
		$sheet->getStyle($range)->getBorders()->getAllBorders()
			->setBorderStyle($style)->getColor()->setARGB($argb);
	}

	/**
	 * Convert Excel column width units to pixels.
	 * 1 unit ≈ 9.1 pixels for 12pt font (higher than standard 7.1 for 11pt).
	 */
	protected function getPixelsFromWidth($units)
	{
		return $units * 9.1;
	}

	/**
	 * Convert Excel row height points to pixels.
	 * 1 point = 1.333 pixels.
	 */
	protected function getPixelsFromHeight($points)
	{
		return $points * 1.333;
	}

	// ── Sheet setup ──────────────────────────────────────────────────────────

	/**
	 * Initialise a worksheet: tab title, column widths, print settings.
	 *
	 * Fixed-width columns:
	 *   A  = 14  (logo zone)
	 *   B  = 14  (logo zone)
	 *   C  = 22  (header label column + 1st data column)
	 *   D  = 35  (header value column + 2nd data column)
	 * Auto-size:
	 *   E onwards (remaining data columns)
	 *
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
	 * @param string $title         Tab label (truncated to 31 chars)
	 * @param int    $nb_data_cols  Total data columns (data fields + verif col)
	 */
	public function setupSheet($sheet, $title, $nb_data_cols)
	{
		$sheet->setTitle(mb_strtoupper(mb_substr($title, 0, 31)));

		// Column A and B are fixed for the logo zone
		$sheet->getColumnDimension('A')->setWidth(12);
		$sheet->getColumnDimension('B')->setWidth(35);

		// Column C is used for header labels, we give it a modest minimum
		$sheet->getColumnDimension('C')->setWidth(20);

		// Columns D onwards: AutoSize for data
		for ($i = 3; $i < $nb_data_cols; $i++) {
			$col_letter = $this->col($i);
			$sheet->getColumnDimension($col_letter)->setAutoSize(true);
		}

		$sheet->getDefaultRowDimension()->setRowHeight(24);
		$this->workbook->getDefaultStyle()->getAlignment()
			->setVertical(Alignment::VERTICAL_CENTER);
		$this->workbook->getDefaultStyle()->getFont()->setSize(12); // Set base font larger

		$sheet->getSheetView()->setZoomScale(110); // Native zoom level for better viewing on screen

		// Print: portrait A4, fit to 1 page wide
		$ps = $sheet->getPageSetup();
		$ps->setOrientation(PageSetup::ORIENTATION_PORTRAIT);
		$ps->setPaperSize(PageSetup::PAPERSIZE_A4);
		$ps->setFitToPage(true);
		$ps->setFitToWidth(1);
		$ps->setFitToHeight(0);
		$sheet->getHeaderFooter()->setOddFooter('&C&P / &N');
	}

	// ── Header ───────────────────────────────────────────────────────────────

	/**
	 * Render the report header block.
	 *
	 * The header spans exactly the data table width (minimum 5 cols = A:E so
	 * the D:last merged value cell always has room). This avoids an empty column
	 * appearing beyond the data table.
	 *
	 * Layout:
	 *   Row  1  │ [RAPPORT DE VERIFICATION 2025 — A:F merged, dark blue]
	 *   Row  2  │ Logo (A:B) │ C=label bold │ D=value │ E:F=bg only
	 *   Row  3  │ Logo       │ Technicien
	 *   Row  4  │ Logo       │ Commercial
	 *   Row  5  │ Logo       │ Client
	 *   Row  6  │ Logo       │ Adresse client
	 *   Row  7  │ Logo       │ Tél
	 *   Row  8  │ Soc. name  │ Code client
	 *   Row  9  │ Soc. addr  │ Type d'organe
	 *   Row 10  │ (spacer)
	 *   → returns row 11 (ready for column headers)
	 *
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
	 * @param int     $row_start
	 * @param int     $nb_data_cols  (used to size the separator line below header)
	 * @param Societe $customer
	 * @param string  $parclabel
	 * @return int  First row after the header block
	 */
	public function customHeader($sheet, $row_start, $nb_data_cols, $customer, $parclabel = '', $inter_date = null)
	{
		global $mysoc, $user, $conf, $langs;

		// Header spans exactly the data table width (A to H_LAST), min Col D (index 3)
		$h_col_idx = max($nb_data_cols - 1, 3);
		$H_LAST    = $this->col($h_col_idx);

		$row = $row_start;

		// ── Row 1 : Title ────────────────────────────────────────────────
		$title_range = 'A' . $row . ':' . $H_LAST . $row;
		$sheet->mergeCells($title_range);
		$sheet->setCellValue('A' . $row, $langs->transnoentities('gp_advexp_title') . ' ' . date('Y'));
		$this->applyFill($sheet, $title_range, self::COLOR_TITLE_BG);
		$this->applyFontColor($sheet, $title_range, self::COLOR_TITLE_FG);
		$sheet->getStyle($title_range)->getFont()->setBold(true)->setSize(20);
		$sheet->getStyle($title_range)->getAlignment()
			->setHorizontal(Alignment::HORIZONTAL_CENTER)
			->setVertical(Alignment::VERTICAL_CENTER);
		$sheet->getRowDimension($row)->setRowHeight(48);
		$row++;

		// ── Rows 2-6 : Logo zone (A:B merged for 5 rows) ────────────────
		$logo_start = $row;
		$logo_end   = $row + 4;
		$sheet->mergeCells('A' . $logo_start . ':B' . $logo_end);
		$this->applyFill($sheet, 'A' . $logo_start . ':B' . $logo_end, self::COLOR_LOGO_BG);

		if (!empty($mysoc->logo) && is_readable($conf->mycompany->dir_output . '/logos/' . $mysoc->logo)) {
			$logo_path = $conf->mycompany->dir_output . '/logos/' . $mysoc->logo;
			$target_height_px = 80;

			// Detect original logo size to calculate scaled width
			$size = getimagesize($logo_path);
			$orig_w = $size[0];
			$orig_h = $size[1];
			$target_width_px = ($orig_w / $orig_h) * $target_height_px;

			// Calculate container dimensions in pixels
			// Col A: 12 units, Col B: 35 units => 47 units
			$container_w_px = $this->getPixelsFromWidth(12 + 35);
			// Rows 2-6: 5 rows at sheet default height (24pt) => 120pt
			$container_h_px = $this->getPixelsFromHeight(5 * 24);

			// Center it!
			$offX = max(0, ($container_w_px - $target_width_px) / 2);
			$offY = max(0, ($container_h_px - $target_height_px) / 2);

			$this->addImage(
				'A' . $logo_start,
				$logo_path,
				'Logo',
				'Logo',
				$target_height_px,
				$offX,
				$offY
			);
		} else {
			$sheet->setCellValue('A' . $logo_start, $mysoc->nom);
			$sheet->getStyle('A' . $logo_start)->getFont()->setBold(true)->setSize(16);
			$sheet->getStyle('A' . $logo_start)->getAlignment()
				->setHorizontal(Alignment::HORIZONTAL_CENTER)
				->setVertical(Alignment::VERTICAL_CENTER)
				->setWrapText(true);
		}

		// Prepare Company Name and Address coordinates
		$company_name_row = $row + 5;
		$company_addr1 = $row + 6;
		$company_zip = $row + 7;
		$company_phone_start = $row + 8;
		$company_phone_end = $row + 8;

		$sheet->mergeCells('A' . $company_name_row . ':B' . $company_name_row);
		$sheet->setCellValue('A' . $company_name_row, $mysoc->nom);
		$sheet->getStyle('A' . $company_name_row)->getFont()->setBold(true)->setSize(13);
		$sheet->getStyle('A' . $company_name_row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
		$this->applyFill($sheet, 'A' . $company_name_row . ':B' . $company_name_row, self::COLOR_LOGO_BG);

		$sheet->mergeCells('A' . $company_addr1 . ':B' . $company_addr1);
		$sheet->setCellValue('A' . $company_addr1, $mysoc->address);
		$sheet->getStyle('A' . $company_addr1)->getFont()->setSize(11);
		$sheet->getStyle('A' . $company_addr1)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
		$this->applyFill($sheet, 'A' . $company_addr1 . ':B' . $company_addr1, self::COLOR_LOGO_BG);

		$sheet->mergeCells('A' . $company_zip . ':B' . $company_zip);
		$sheet->setCellValue('A' . $company_zip, trim($mysoc->zip . ' ' . $mysoc->town));
		$sheet->getStyle('A' . $company_zip)->getFont()->setSize(11);
		$sheet->getStyle('A' . $company_zip)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
		$this->applyFill($sheet, 'A' . $company_zip . ':B' . $company_zip, self::COLOR_LOGO_BG);

		$sheet->mergeCells('A' . $company_phone_start . ':B' . $company_phone_end);
		$contact_info = trim($mysoc->phone . ' - ' . $mysoc->email, ' -');
		$sheet->setCellValue('A' . $company_phone_start, $contact_info);
		$sheet->getStyle('A' . $company_phone_start)->getFont()->setSize(11);
		$sheet->getStyle('A' . $company_phone_start)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
		$this->applyFill($sheet, 'A' . $company_phone_start . ':B' . $company_phone_end, self::COLOR_LOGO_BG);

		// ── Info rows alongside the logo (C=label, D=value, E:H_LAST=bg) ─────
		$date_ts = !empty($inter_date) ? $inter_date : dol_stringtotime(date('Y-m-d'));
		$this->writeInfoRow(
			$sheet,
			$row,
			$H_LAST,
			$langs->transnoentities('gp_advexp_datevisit'),
			dol_print_date($date_ts, '%d/%m/%Y')
		);
		$row++;

		$this->writeInfoRow(
			$sheet,
			$row,
			$H_LAST,
			$langs->transnoentities('gp_advexp_user'),
			trim($user->firstname . ' ' . $user->lastname)
		);
		$row++;

		$salesrep_parts = array();
		foreach ($customer->getSalesRepresentatives($user) as $sr) {
			$salesrep_parts[] = trim($sr['firstname'] . ' ' . $sr['lastname']);
		}
		$this->writeInfoRow(
			$sheet,
			$row,
			$H_LAST,
			$langs->transnoentities('gp_advexp_commercial'),
			implode(' / ', $salesrep_parts)
		);
		$row++;

		// Empty row (saut de ligne) separating visit details from customer details
		$this->writeInfoRow($sheet, $row, $H_LAST, '', '');
		$row++;

		$this->writeInfoRow(
			$sheet,
			$row,
			$H_LAST,
			$langs->transnoentities('Customer'),
			$customer->nom
		);
		$row++;

		$client_address = trim($customer->address . ', ' . $customer->zip . ' ' . $customer->town, ', ');
		$this->writeInfoRow(
			$sheet,
			$row,
			$H_LAST,
			$langs->transnoentities('Address'),
			$client_address
		);
		$row++;

		$this->writeInfoRow(
			$sheet,
			$row,
			$H_LAST,
			$langs->transnoentities('Phone'),
			!empty($customer->phone) ? $customer->phone : '—'
		);
		$row++;

		$this->writeInfoRow(
			$sheet,
			$row,
			$H_LAST,
			$langs->transnoentities('CustomerCode'),
			$customer->code_client
		);
		$row++;

		if (!empty($parclabel)) {
			$this->writeInfoRow(
				$sheet,
				$row,
				$H_LAST,
				$langs->transnoentities('gp_advexp_parctype'),
				$parclabel
			);
			$row++;
		}

		if (empty($parclabel)) {
			// Always add one empty spacer row at the bottom of the master header block 
			// to ensure the left-side company info is fully enclosed and has padding.
			$this->writeInfoRow($sheet, $row, $H_LAST, '', '');
			$row++;
		}

		// ── Outer border around the whole header block (A:F) ────────────
		$this->applyBorderOutline(
			$sheet,
			'A' . $row_start . ':' . $H_LAST . ($row - 1),
			Border::BORDER_MEDIUM,
			self::COLOR_BORDER_DARK
		);
		// Thick bottom border on the header block as visual separator
		$sheet->getStyle('A' . ($row - 1) . ':' . $H_LAST . ($row - 1))
			->getBorders()->getBottom()
			->setBorderStyle(Border::BORDER_MEDIUM)
			->getColor()->setARGB(self::COLOR_BORDER_DARK);

		// ── Empty spacer row between header and data table ───────────────
		$sheet->getRowDimension($row)->setRowHeight(24);
		$row++;

		return $row;
	}

	/**
	 * Write a section title bar for consolidated reports.
	 *
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
	 * @param int    $row
	 * @param int    $nb_data_cols
	 * @param string $label
	 * @return int  Next row
	 */
	public function writeSectionTitle($sheet, $row, $nb_data_cols, $label)
	{
		$h_col_idx = $nb_data_cols - 1;
		$last  = $this->col($h_col_idx);
		$range = 'A' . $row . ':' . $last . $row;
		$sheet->mergeCells($range);
		$title_text = 'ORGANE CONTRÔLÉ : ' . mb_strtoupper($label);
		$sheet->setCellValue('A' . $row, $title_text);
		$this->applyFill($sheet, $range, self::COLOR_TITLE_BG);
		$this->applyFontColor($sheet, $range, self::COLOR_TITLE_FG);
		$sheet->getStyle($range)->getFont()->setBold(true)->setSize(14);
		$sheet->getStyle($range)->getAlignment()
			->setHorizontal(Alignment::HORIZONTAL_LEFT)
			->setVertical(Alignment::VERTICAL_CENTER)
			->setIndent(1);
		$this->applyBorderOutline($sheet, $range, Border::BORDER_MEDIUM, self::COLOR_BORDER_DARK);
		$sheet->getRowDimension($row)->setRowHeight(30);
		return $row + 1;
	}

	/**
	 * Write one header info row.
	 *
	 * Fixed 2-column layout inside the header block:
	 *   C = label (bold, dark navy)
	 *   D = value (normal, near-black)
	 *   E:H_LAST = empty cells with the same info background (visual fill only)
	 *
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
	 * @param int    $row
	 * @param string $h_last   Last column letter of the header block (always 'F')
	 * @param string $label
	 * @param string $value
	 */
	protected function writeInfoRow($sheet, $row, $h_last, $label, $value, $val_align = 'left')
	{
		// Label cell (C ONLY - no merge with D)
		$sheet->setCellValue('C' . $row, $label);
		$sheet->getStyle('C' . $row)->getFont()->setBold(true)->setSize(13);
		$this->applyFontColor($sheet, 'C' . $row, self::COLOR_INFO_LABEL);
		$sheet->getStyle('C' . $row)->getAlignment()
			->setVertical(Alignment::VERTICAL_CENTER);

		// Value cell (D:h_last merged)
		$val_range = 'D' . $row . ':' . $h_last . $row;
		$sheet->mergeCells($val_range);
		$sheet->setCellValue('D' . $row, $value);
		$sheet->getStyle('D' . $row)->getFont()->setSize(13);
		$this->applyFontColor($sheet, 'D' . $row, self::COLOR_INFO_VALUE);
		$align_horiz = ($val_align === 'right') ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT;
		$sheet->getStyle('D' . $row)->getAlignment()
			->setHorizontal($align_horiz)
			->setVertical(Alignment::VERTICAL_CENTER)
			->setWrapText(true);

		// Excel auto-fit row height does not work on merged cells.
		// We must estimate the required height manually.
		$lines = 0;
		$val_norm = str_replace(array("\r\n", "\r"), "\n", $value);
		$texts = explode("\n", $val_norm);
		foreach ($texts as $t) {
			// Assume the merged column might be narrow (around 30-35 chars per line)
			$lines += max(1, ceil(mb_strlen($t) / 30));
		}
		$estimated_height = max(24, $lines * 18 + 6);
		$sheet->getRowDimension($row)->setRowHeight($estimated_height);

		if ($val_align === 'right') {
			$sheet->getStyle('D' . $row)->getAlignment()->setIndent(1);
		}

		// Background on the full info zone (C:h_last)
		$info_range = 'C' . $row . ':' . $h_last . $row;
		$this->applyFill($sheet, $info_range, self::COLOR_INFO_BG);

		// Subtle bottom border on the info zone
		$sheet->getStyle($info_range)->getBorders()->getBottom()
			->setBorderStyle(Border::BORDER_HAIR)
			->getColor()->setARGB(self::COLOR_BORDER_SOFT);

		// Left border on C (separator between logo and info)
		$sheet->getStyle('C' . $row)->getBorders()->getLeft()
			->setBorderStyle(Border::BORDER_THIN)
			->getColor()->setARGB(self::COLOR_BORDER_DARK);
	}

	// ── Column headers ───────────────────────────────────────────────────────

	/**
	 * Write the styled column header row.
	 *
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
	 * @param int   $row
	 * @param array $col_labels  Ordered list of column labels
	 * @return int  Next row number
	 */
	public function writeColumnHeaders($sheet, $row, array $col_labels)
	{
		$nb = count($col_labels);
		$col_idx = 0;
		foreach ($col_labels as $label) {
			$cell = $this->col($col_idx) . $row;
			$sheet->setCellValue($cell, $label);
			$this->applyFill($sheet, $cell, self::COLOR_COLHDR_BG);
			$this->applyFontColor($sheet, $cell, self::COLOR_COLHDR_FG);
			$sheet->getStyle($cell)->getFont()->setBold(true)->setSize(13);
			$sheet->getStyle($cell)->getAlignment()
				->setHorizontal(Alignment::HORIZONTAL_CENTER)
				->setVertical(Alignment::VERTICAL_CENTER)
				->setWrapText(true);
			// White inner borders between columns
			$sheet->getStyle($cell)->getBorders()->getAllBorders()
				->setBorderStyle(Border::BORDER_THIN)
				->getColor()->setARGB(self::COLOR_BORDER_DARK);
			$col_idx++;
		}
		// Outer border on the whole header row
		$sheet->getRowDimension($row)->setRowHeight(28); // Refined header height
		return $row + 1;
	}

	// ── Data rows ────────────────────────────────────────────────────────────

	/**
	 * Write one styled data row with alternating colours.
	 *
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
	 * @param int        $row
	 * @param array      $values      Cell values (without the verif column)
	 * @param bool|null  $isVerified  true/false = status ; null = empty fill row
	 * @param bool       $isOdd       Odd row → light blue background
	 * @param array      $align_map   0-based col index => 'left'|'center'|'right'
	 * @return int  Next row number
	 */
	public function writeDataRow($sheet, $row, array $values, $isOdd, array $align_map = array())
	{
		$row_bg = $isOdd ? self::COLOR_ROW_ODD : 'FFFFFFFF';

		// Refined row height (between standard 15 and previous 38)
		$sheet->getRowDimension($row)->setRowHeight(24);

		$col_idx = 0;
		foreach ($values as $value) {
			$cell = $this->col($col_idx) . $row;
			$sheet->setCellValue($cell, $value);
			$sheet->getStyle($cell)->getFont()->setSize(12);
			$this->applyFill($sheet, $cell, $row_bg);

			// Enable wrapping ONLY for the 'Produit' column (Index 1)
			$wrap = ($col_idx === 1);
			$sheet->getStyle($cell)->getAlignment()
				->setWrapText($wrap)
				->setVertical(Alignment::VERTICAL_CENTER);
			// Soft inner borders
			$sheet->getStyle($cell)->getBorders()->getAllBorders()
				->setBorderStyle(Border::BORDER_HAIR)
				->getColor()->setARGB(self::COLOR_BORDER_SOFT);
			// Left border slightly stronger for readability
			$sheet->getStyle($cell)->getBorders()->getLeft()
				->setBorderStyle(Border::BORDER_THIN)
				->getColor()->setARGB(self::COLOR_BORDER_SOFT);

			// Alignment logic: use the provided map or auto-detect numeric/date values
			$h = Alignment::HORIZONTAL_LEFT;
			if (!empty($align_map[$col_idx])) {
				switch ($align_map[$col_idx]) {
					case 'center':
						$h = Alignment::HORIZONTAL_CENTER;
						break;
					case 'right':
						$h = Alignment::HORIZONTAL_RIGHT;
						break;
				}
			} else {
				// Auto-center years, numbers, and dates (XX/XX/XXXX)
				$clean_val = trim((string) $value);
				if (is_numeric($clean_val) || preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $clean_val)) {
					$h = Alignment::HORIZONTAL_CENTER;
				}
			}
			$sheet->getStyle($cell)->getAlignment()->setHorizontal($h);
			$col_idx++;
		}

		return $row + 1;
	}

	// ── Summary row ──────────────────────────────────────────────────────────

	/**
	 * Write the summary row below the data table.
	 *
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
	 * @param int $row
	 * @param int $verified
	 * @param int $total
	 * @param int $nb_data_cols  Total columns (data fields + verif col)
	 * @return int  Next row number
	 */
	public function writeSummaryRow($sheet, $row, $verified, $total, $nb_data_cols)
	{
		$last  = $this->col($nb_data_cols - 1);
		$range = 'A' . $row . ':' . $last . $row;
		$sheet->mergeCells($range);
		$sheet->setCellValue('A' . $row, $verified . ' / ' . $total . ' élément(s) vérifié(s)');
		$this->applyFill($sheet, $range, self::COLOR_SUMMARY_BG);
		$this->applyFontColor($sheet, $range, self::COLOR_INFO_LABEL);
		$sheet->getStyle($range)->getFont()->setBold(true)->setSize(13);
		$sheet->getStyle($range)->getAlignment()
			->setHorizontal(Alignment::HORIZONTAL_RIGHT)
			->setVertical(Alignment::VERTICAL_CENTER)
			->setIndent(2); // Margin at the right
		$this->applyBorderOutline($sheet, $range, Border::BORDER_MEDIUM, self::COLOR_BORDER_DARK);
		$sheet->getRowDimension($row)->setRowHeight(26); // Refined footer height
		return $row + 1;
	}

	// ── Image ────────────────────────────────────────────────────────────────

	public function addImage($coords, $image_path, $image_name = '', $image_description = '', $height = 80, $offsetX = 10, $offsetY = 8)
	{
		$sheet   = $this->workbook->getActiveSheet();
		$drawing = new Drawing();
		$drawing->setName($image_name);
		$drawing->setDescription($image_description);
		$drawing->setPath($image_path);
		$drawing->setResizeProportional(true);
		$drawing->setHeight($height);
		$drawing->setCoordinates($coords);
		$drawing->setOffsetX($offsetX);
		$drawing->setOffsetY($offsetY);
		$drawing->setWorksheet($sheet);
	}
}

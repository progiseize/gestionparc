<?php
/* Copyright (C) 2024 Progiseize */

require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once TCPDF_PATH . 'tcpdf.php';
dol_include_once('/gestionparc/class/gestionparcphoto.class.php');

/**
 * GestionParcPDF — Faithful PDF translation of the Excel "RAPPORT COMPLET" sheet.
 *
 * Design system — mirrors GestionParcExport colour constants exactly:
 *
 *   TITLE_BG   = #173652  → full-width title bars (navy)
 *   TITLE_FG   = #FFFFFF  → text on navy
 *   COLHDR_BG  = #173652  → column header fill (navy, same as title)
 *   COLHDR_FG  = #FFFFFF  → column header text (white)
 *   INFO_LABEL = #173652  → bold label text in info block (navy)
 *   INFO_VALUE = #333333  → regular value text
 *   ROW_ODD    = #F5F5F5  → alternate data row
 *   SUMMARY_BG = #E8E8E8  → summary row
 *   BDR_DARK   = #173652  → outer/structural borders (navy, MEDIUM)
 *   BDR_SOFT   = #D0D0D0  → internal cell hairlines
 *
 * Typography (PDF ≈ 60 % of Excel pt values for correct visual weight):
 *   Title bars  : 13 pt bold
 *   Col headers : 8.5 pt bold
 *   Info labels : 8 pt bold
 *   Info values : 8 pt regular
 *   Data cells  : 8.5 pt regular
 *   Summary     : 8.5 pt bold
 */
class GestionParcPDF extends TCPDF
{
    private $db;
    /** @var object */  private $intervention;
    /** @var object */  private $customer;
    /** @var array */   private $headerOverride = array();

    // ── Palette — identical to GestionParcExport constants ─────────────────
    const TITLE_BG   = array(23,  54,  82);
    const TITLE_FG   = array(255, 255, 255);
    const INFO_LABEL = array(23,  54,  82);
    const INFO_VALUE = array(51,  51,  51);
    const ROW_ODD    = array(245, 245, 245);
    const ROW_EVEN   = array(255, 255, 255);
    const SUMMARY_BG = array(232, 232, 232);
    const BDR_DARK   = array(23,  54,  82);
    const BDR_SOFT   = array(208, 208, 208);
    const TEXT_MUTED = array(140, 150, 162);

    // ── Geometry ─────────────────────────────────────────────────────────
    const ML = 15;   // left margin mm
    const MR = 15;   // right margin mm
    const CW = 180;  // content width = 210 − ML − MR

    // ── Constructor ───────────────────────────────────────────────────────

    public function __construct(
        $db,
        $orientation = 'P',
        $unit = 'mm',
        $format = 'A4',
        $unicode = true,
        $encoding = 'UTF-8',
        $diskcache = false,
        $pdfa = false
    ) {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
        $this->db = $db;
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /** Strip HTML entities for PDF output */
    private function t($s)
    {
        return dol_html_entity_decode((string)$s, ENT_QUOTES | ENT_HTML5);
    }

    /** SetFillColor from array */
    private function sf(array $c)
    {
        $this->SetFillColor($c[0], $c[1], $c[2]);
    }
    /** SetTextColor from array */
    private function sc(array $c)
    {
        $this->SetTextColor($c[0], $c[1], $c[2]);
    }
    /** SetDrawColor from array */
    private function sd(array $c)
    {
        $this->SetDrawColor($c[0], $c[1], $c[2]);
    }

    // ── Checkbox list (custom soc fields) ─────────────────────────────────
    //
    //   ☒ APSAD R4    ☐ Code du Travail
    //
    // Every option of the field is rendered, checked or not. Options wrap to a
    // new line when they no longer fit in $maxw. Pass $draw = false to only
    // measure the block height (used by the row height computation).

    const CB_SIZE = 3.0;   // box side (mm)
    const CB_GAP  = 1.5;   // box → label
    const CB_SEP  = 6.0;   // option → option
    const CB_LH   = 5.0;   // line height

    private function checkboxList($x, $y, $maxw, array $options, $draw = true)
    {
        $this->SetFont('helvetica', '', 8);

        // Split options into lines fitting $maxw
        $lines   = array(array());
        $line_w  = 0;
        foreach ($options as $opt) {
            $opt_w = self::CB_SIZE + self::CB_GAP + $this->GetStringWidth($opt['label']);
            $last  = count($lines) - 1;
            $need  = $opt_w + (empty($lines[$last]) ? 0 : self::CB_SEP);
            if (!empty($lines[$last]) && $line_w + $need > $maxw) {
                $lines[] = array();
                $last++;
                $line_w = 0;
                $need = $opt_w;
            }
            $lines[$last][] = $opt;
            $line_w += $need;
        }

        $height = count($lines) * self::CB_LH;
        if (!$draw) return $height;

        $ly = $y;
        foreach ($lines as $line) {
            $lx = $x;
            foreach ($line as $opt) {
                $bx = $lx;
                $by = $ly + (self::CB_LH - self::CB_SIZE) / 2;

                $this->sd(self::INFO_LABEL);
                $this->sf(self::ROW_EVEN);
                $this->SetLineWidth(0.35);
                $this->Rect($bx, $by, self::CB_SIZE, self::CB_SIZE, 'DF');

                if (!empty($opt['checked'])) {
                    $this->SetLineWidth(0.45);
                    $this->Line($bx + 0.6, $by + 0.6, $bx + self::CB_SIZE - 0.6, $by + self::CB_SIZE - 0.6);
                    $this->Line($bx + self::CB_SIZE - 0.6, $by + 0.6, $bx + 0.6, $by + self::CB_SIZE - 0.6);
                }

                $lbl_w = $this->GetStringWidth($opt['label']);
                $this->sc(self::INFO_VALUE);
                $this->SetXY($bx + self::CB_SIZE + self::CB_GAP, $ly);
                $this->Cell($lbl_w + 0.5, self::CB_LH, $opt['label'], 0, 0, 'L');

                $lx = $bx + self::CB_SIZE + self::CB_GAP + $lbl_w + self::CB_SEP;
            }
            $ly += self::CB_LH;
        }

        return $height;
    }

    // ── TCPDF Header — minimal, no logo, no colour clash ──────────────────

    public function Header()
    {
        global $mysoc, $langs;
        $y = 8;
        $h = 10;

        // Hairline bottom border only — no background colour
        $this->SetFont('helvetica', '', 7.5);
        $this->sc(self::TEXT_MUTED);
        $this->SetXY(self::ML, $y + 2);
        $this->Cell(85, 6, $this->t($mysoc->name), 0, 0, 'L');

        if (!empty($this->intervention)) {
            $this->SetFont('helvetica', 'B', 7.5);
            $this->sc(self::INFO_LABEL);
            $this->SetXY(105, $y + 2);
            $this->Cell(90, 6, $this->t($langs->trans('gp_advexp_title')) . '  —  ' . $this->intervention->ref, 0, 0, 'R');
        }

        $this->sd(self::BDR_SOFT);
        $this->SetLineWidth(0.2);
        $this->Line(self::ML, $y + $h, 210 - self::MR, $y + $h);
    }

    // ── TCPDF Footer ──────────────────────────────────────────────────────

    public function Footer()
    {
        global $mysoc, $langs;

        // ── Separator line ────────────────────────────────────────────────
        $this->SetY(-16);
        $this->sd(self::BDR_SOFT);
        $this->SetLineWidth(0.3);
        $this->Line(self::ML, $this->GetY(), 210 - self::MR, $this->GetY());

        // ── Content: legal info left, page number right ───────────────────
        $this->SetY(-12);
        $y_footer = $this->GetY();

        $legal = array($this->t($mysoc->name));
        if ($mysoc->address) {
            $legal[] = $this->t(trim($mysoc->address . ' ' . $mysoc->zip . ' ' . $mysoc->town));
        }
        $ids = array();
        if ($mysoc->siret)     $ids[] = 'SIRET ' . $mysoc->siret;
        if ($mysoc->tva_intra) {
            $ids[] = $this->t($langs->trans('VATIntraShort')) . ' ' . $mysoc->tva_intra;
        }
        if (!empty($ids)) $legal[] = implode(' — ', $ids);

        // Legal text — left aligned, single line, muted
        $this->SetFont('helvetica', '', 7);
        $this->sc(self::TEXT_MUTED);
        $this->SetXY(self::ML, $y_footer);
        $this->Cell(145, 5, implode('  |  ', $legal), 0, 0, 'L');

        // Page number — right aligned, slightly more prominent
        $this->SetFont('helvetica', 'B', 7.5);
        $this->sc(self::INFO_LABEL);
        $this->SetXY(160, $y_footer);
        $this->Cell(35, 5, $langs->trans('Page') . ' ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
    }

    // ── Entry point ───────────────────────────────────────────────────────

    public function generate($intervention, $report_data, $customer, $output_file)
    {
        global $langs, $mysoc;

        $this->intervention = $intervention;
        $this->customer     = $customer;
        $this->headerOverride = (isset($report_data['header']) && is_array($report_data['header'])) ? $report_data['header'] : array();

        // Ensure translation keys from companies / bills are available
        $langs->loadLangs(array('companies', 'bills'));

        $this->SetCreator('Dolibarr — GestionParc');
        $this->SetAuthor($this->t($mysoc->name));
        $this->SetTitle($this->t($langs->trans('gp_advexp_title')) . ' ' . $intervention->ref);

        $this->setPrintHeader(true);
        $this->setPrintFooter(true);
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(10);
        $this->SetMargins(self::ML, 24, self::MR);
        $this->SetAutoPageBreak(true, 18);

        $this->AddPage();
        $this->drawInfoBlock($intervention, $customer);
        $this->drawObservations(isset($report_data['observations']) ? $report_data['observations'] : '');

        foreach ($report_data['sections'] as $idx => $section) {
            $this->drawSection($section, ($idx === 0));
        }

        // Légendes des organes, regroupées en fin de rapport
        $this->drawLegend($report_data);

        // Annexe photographique (option globale du module)
        if (getDolGlobalInt('GESTIONPARC_EXPORT_PHOTOS')) {
            $this->drawPhotoAnnex($report_data);
        }

        $this->Output($output_file, 'F');
    }

    // ─────────────────────────────────────────────────────────────────────
    // INFO BLOCK
    //
    // Mirrors the Excel header block faithfully:
    //
    //  Row 1   [RAPPORT DE VÉRIFICATION 2026 — full width navy title      ]
    //          ┌─────────────────────┬──────────────────────────────────────┐
    //  Rows    │  [LOGO]             │ C=label navy bold  │ D=value grey     │
    //  2-6     │                     │ Date de passage    │ XX/XX/XXXX        │
    //          │                     │ Technicien         │ SuperAdmin        │
    //          │                     │ Commercial         │ SuperAdmin        │
    //          │                     │ (spacer row)       │                   │
    //  Rows    │  Company name       │ Client             │ TEST Corp         │
    //  7-10    │  Address            │ Adresse            │ 123...            │
    //          │  Phone/Email        │ Téléphone          │ —                 │
    //          │                     │ Code client        │ CU2511-00001      │
    //          └─────────────────────┴──────────────────────────────────────┘
    //
    //  Outer border: BORDER_MEDIUM navy
    //  Info row bottom border: BORDER_HAIR #D0D0D0
    //  Left border on label column: BORDER_THIN navy
    // ─────────────────────────────────────────────────────────────────────

    private function drawInfoBlock($intervention, $customer)
    {
        global $langs, $user, $mysoc, $conf;

        $x0 = self::ML;
        $w  = self::CW;
        $y  = $this->GetY();

        // ── Info rows data preparation ────────────────────────────────────
        $inter_date = !empty($intervention->dateo) ? $intervention->dateo : (!empty($intervention->datec) ? $intervention->datec : dol_stringtotime(date('Y-m-d')));

        // ── Row 1: Title bar ─────────────────────────────────────────────
        $th = 11;
        $this->sf(self::TITLE_BG);
        $this->Rect($x0, $y, $w, $th, 'F');
        $this->SetFont('helvetica', 'B', 14);
        $this->sc(self::TITLE_FG);
        $this->SetXY($x0, $y);
        $this->Cell(
            $w,
            $th,
            mb_strtoupper($this->t($langs->trans('gp_advexp_title'))) . ' ' . dol_print_date($inter_date, '%Y'),
            0,
            1,
            'C'
        );
        $y += $th;

        if (!class_exists('GestionParcVerif')) dol_include_once('/gestionparc/class/gestionparc.class.php');
        $export_users = GestionParcVerif::resolveExportUsers($this->db, $intervention, $customer);
        $ho = $this->headerOverride; // en-tête figé (backport) si présent
        $tech     = !empty($ho['technicien']) ? $ho['technicien'] : $export_users['intervenant'];
        $salesrep = !empty($ho['commercial']) ? $ho['commercial'] : $export_users['commercial'];

        $cust_name    = !empty($ho['client'])      ? $ho['client']      : $customer->name;
        $addr_client  = !empty($ho['address'])     ? $ho['address']     : trim($this->t($customer->address) . ', ' . trim($customer->zip . ' ' . $this->t($customer->town)), ', ');
        $cust_phone   = !empty($ho['phone'])       ? $ho['phone']       : (!empty($customer->phone) ? $customer->phone : '—');
        $cust_code    = !empty($ho['code_client']) ? $ho['code_client'] : $customer->code_client;

        // 7 info rows + 1 spacer at position 3 (0-indexed)
        $info_rows = array(
            array($this->t($langs->trans('gp_advexp_datevisit')),  dol_print_date($inter_date, '%d/%m/%Y')),
            array($this->t($langs->trans('gp_advexp_user')),       $this->t($tech)),
            array($this->t($langs->trans('gp_advexp_commercial')), $this->t($salesrep)),
            null,   // spacer
            array($this->t($langs->trans('Customer')),             $this->t($cust_name)),
            array($this->t($langs->trans('Address')),              $this->t($addr_client)),
            array($this->t($langs->trans('Phone')),                  $cust_phone),
            array($this->t($langs->trans('CustomerCode')),         $cust_code),
        );

        // Champs personnalisés (bas du header) : toutes les cases, cochées ou non
        foreach (GestionParcVerif::getCustomSocFields() as $code => $def) {
            $opts = GestionParcVerif::getCustomSocFieldOptionsState($this->db, $code, $intervention, $customer);
            if (empty($opts)) continue;
            foreach ($opts as $i => $opt) {
                $opts[$i]['label'] = $this->t($opt['label']);
            }
            $info_rows[] = array($this->t($langs->trans($def['label'])), '', $opts);
        }

        // ── Geometry ─────────────────────────────────────────────────────
        $logo_w = 58;   // A:B equivalent
        $info_w = $w - $logo_w;
        $lbl_w  = 52;   // C column width inside info zone
        $val_w  = $info_w - $lbl_w;

        // Row heights calculation
        $rh        = 6.5;  // default data row height
        $spacer_h  = 4.0;  // spacer between visit and client groups
        $calculated_heights = array();
        $block_h = 0;

        $this->SetFont('helvetica', '', 8);
        foreach ($info_rows as $row) {
            if ($row === null) {
                $calculated_heights[] = $spacer_h;
                $block_h += $spacer_h;
            } else {
                if (!empty($row[2])) {
                    $h_val = $this->checkboxList(0, 0, $val_w - 4, $row[2], false);
                } else {
                    $h_val = $this->getStringHeight($val_w - 2, $row[1]);
                }
                $row_h = max($rh, $h_val + 2); // padding
                $calculated_heights[] = $row_h;
                $block_h += $row_h;
            }
        }

        // ── Backgrounds ──────────────────────────────────────────────────
        $this->sf(self::ROW_EVEN);
        $this->Rect($x0, $y, $logo_w, $block_h, 'F');
        $this->Rect($x0 + $logo_w, $y, $info_w, $block_h, 'F');

        // ── Logo zone (Rows 2-6 equivalent: upper part of left column) ───
        $logo_zone_h = 0;
        for ($i = 0; $i <= 3; $i++) {
            $logo_zone_h += $calculated_heights[$i];
        } // roughly first 4 rows
        $logo_bottom = $y + 3;
        if (!empty($mysoc->logo) && is_readable($conf->mycompany->dir_output . '/logos/' . $mysoc->logo)) {
            $logo_path = $conf->mycompany->dir_output . '/logos/' . $mysoc->logo;
            $logo_h    = min(26, $logo_zone_h - 4);
            // Center logo horizontally within the left column
            $logo_x = $x0 + 3;
            $img_size = getimagesize($logo_path);
            if ($img_size && $img_size[1] > 0) {
                $aspect        = $img_size[0] / $img_size[1];
                $logo_w_mm     = $logo_h * $aspect;
                $logo_x        = $x0 + max(2, ($logo_w - $logo_w_mm) / 2);
            }
            $this->Image($logo_path, $logo_x, $y + 3, 0, $logo_h);
            $logo_bottom = $y + 3 + $logo_h + 2;
        }

        // ── Company name + address (Rows 7-10 equivalent) ────────────────
        // Company name
        if (!empty($mysoc->name)) {
            $this->SetFont('helvetica', 'B', 8.5);
            $this->sc(self::INFO_LABEL);
            $this->SetXY($x0 + 2, $logo_bottom);
            $this->MultiCell($logo_w - 4, 4.5, $this->t($mysoc->name), 0, 'C', false, 1);
            $logo_bottom = $this->GetY();
        }
        $this->SetFont('helvetica', '', 7.5);
        $this->sc(self::TEXT_MUTED);
        foreach (
            array(
                $mysoc->address,
                trim($mysoc->zip . ' ' . $mysoc->town),
                $mysoc->phone,
                $mysoc->email
            ) as $line
        ) {
            if (empty(trim((string)$line))) continue;
            if ($logo_bottom + 3.5 > $y + $block_h) break;
            $this->SetXY($x0 + 2, $logo_bottom);
            $this->MultiCell($logo_w - 4, 3.5, $this->t($line), 0, 'C', false, 1);
            $logo_bottom = $this->GetY();
        }

        // ── Info rows (right zone) ────────────────────────────────────────
        $rx  = $x0 + $logo_w;
        $ry  = $y;

        foreach ($info_rows as $idx => $row) {
            $row_height = $calculated_heights[$idx];

            if ($row === null) {
                // Spacer: very light gray band + thin separator line
                $this->sf(array(250, 250, 252));
                $this->Rect($rx, $ry, $info_w, $row_height, 'F');
                $this->sd(self::BDR_SOFT);
                $this->SetLineWidth(0.5);
                $this->Line($rx + 2, $ry + $row_height / 2, $rx + $info_w - 2, $ry + $row_height / 2);
                $ry += $row_height;
                continue;
            }

            list($label, $value) = $row;

            // Alternating background (skip spacer for counting)
            $data_idx = ($idx > 3) ? $idx - 1 : $idx; // normalize after spacer
            if ($data_idx % 2 === 1) {
                $this->sf(self::ROW_ODD);
                $this->Rect($rx, $ry, $info_w, $row_height, 'F');
            }

            // Bottom hairline
            $this->sd(self::BDR_SOFT);
            $this->SetLineWidth(0.1);
            $this->Line($rx, $ry + $row_height, $rx + $info_w, $ry + $row_height);

            // Left border on label column (BORDER_THIN navy — exact match to Excel writeInfoRow)
            $this->sd(self::BDR_DARK);
            $this->SetLineWidth(0.3);
            $this->Line($rx, $ry, $rx, $ry + $row_height);

            // Label (bold, navy — COLOR_INFO_LABEL)
            $this->SetFont('helvetica', 'B', 8);
            $this->sc(self::INFO_LABEL);
            $h_lbl = $this->getStringHeight($lbl_w - 3, $label);
            $this->SetXY($rx + 3, $ry + max(0, ($row_height - $h_lbl) / 2));
            $this->MultiCell($lbl_w - 3, $h_lbl, $label, 0, 'L', false, 0);

            // Value : cases à cocher (champs personnalisés) ou texte
            $this->SetFont('helvetica', '', 8);
            $this->sc(self::INFO_VALUE);
            if (!empty($row[2])) {
                $h_val = $this->checkboxList(0, 0, $val_w - 4, $row[2], false);
                $this->checkboxList($rx + $lbl_w + 2, $ry + max(0, ($row_height - $h_val) / 2), $val_w - 4, $row[2]);
            } else {
                $h_val = $this->getStringHeight($val_w - 2, $value);
                $this->SetXY($rx + $lbl_w + 2, $ry + max(0, ($row_height - $h_val) / 2));
                $this->MultiCell($val_w - 2, 4.5, $value, 0, 'L', false, 0);
            }

            $ry += $row_height;
        }

        // ── Block outer border (BORDER_MEDIUM navy — Excel style) ─────────
        $this->sd(self::BDR_DARK);
        $this->SetLineWidth(0.6);
        $this->Rect($x0, $y - $th, $w, $th + $block_h, 'D');  // title + body
        // Horizontal line between title and body
        $this->Line($x0, $y, $x0 + $w, $y);
        // Vertical line between logo zone and info zone
        $this->SetLineWidth(0.3);
        $this->sd(self::BDR_SOFT);
        $this->Line($x0 + $logo_w, $y, $x0 + $logo_w, $y + $block_h);
        // Vertical line between label col and value col
        $this->SetLineWidth(0.15);
        $this->Line($rx + $lbl_w, $y, $rx + $lbl_w, $y + $block_h);

        $this->SetLineWidth(0.2);
        $this->SetY($y + $block_h + 7);
    }

    // ─────────────────────────────────────────────────────────────────────
    // OBSERVATIONS
    //
    // Reprend le commentaire saisi dans la pop-up de clôture de la vérif,
    // juste sous le bloc d'en-tête. Rien n'est dessiné si le champ est vide.
    //
    //   ┌─ OBSERVATIONS ────────────────────────────────────────────────┐  navy
    //   │ Texte libre, sur autant de lignes que nécessaire.             │
    //   └───────────────────────────────────────────────────────────────┘
    // ─────────────────────────────────────────────────────────────────────

    private function drawObservations($text)
    {
        global $langs;

        // Le champ passe par restricthtml : on ramène les <br> à des sauts de ligne
        // et on retire le balisage résiduel avant de décoder les entités.
        $text = preg_replace('/<br\s*\/?>/i', "\n", (string) $text);
        $text = trim($this->t(strip_tags($text)));
        if ($text === '') return;

        $x0 = self::ML;
        $w  = self::CW;
        $th = 8;    // bandeau de titre
        $pad = 3;   // marge intérieure du corps

        $this->SetFont('helvetica', '', 8.5);
        $body_h = max(8, $this->getStringHeight($w - 2 * $pad, $text) + 2 * $pad);

        // Le bloc reste solidaire de son titre : on bascule de page si besoin
        if ($this->GetY() + $th + $body_h > $this->getPageHeight() - 20) {
            $this->AddPage();
        }

        $y = $this->GetY();

        // Bandeau de titre
        $this->sf(self::TITLE_BG);
        $this->Rect($x0, $y, $w, $th, 'F');
        $this->SetFont('helvetica', 'B', 10);
        $this->sc(self::TITLE_FG);
        $this->SetXY($x0, $y);
        $this->Cell($w, $th, mb_strtoupper($this->t($langs->trans('gp_advexp_observations'))), 0, 0, 'C');

        // Corps
        $this->sf(self::ROW_EVEN);
        $this->Rect($x0, $y + $th, $w, $body_h, 'F');
        $this->SetFont('helvetica', '', 8.5);
        $this->sc(self::INFO_VALUE);
        $this->SetXY($x0 + $pad, $y + $th + $pad);
        $this->MultiCell($w - 2 * $pad, 4.5, $text, 0, 'L', false, 1);

        // Bordure extérieure navy, comme les tableaux d'organe
        $this->sd(self::BDR_DARK);
        $this->SetLineWidth(0.55);
        $this->Rect($x0, $y, $w, $th + $body_h, 'D');
        $this->SetLineWidth(0.2);

        $this->SetY($y + $th + $body_h + 7);
    }

    // ─────────────────────────────────────────────────────────────────────
    // SECTION (one per parc type)
    //
    // Mirrors "RAPPORT COMPLET" sheet layout:
    //   ┌─ ORGANE CONTRÔLÉ : TEST 123 ──────────────────────────────────┐  navy title
    //   │ Col1  │  Col2  │  Type inter  │  Produit                      │  navy headers
    //   ├───────┼────────┼──────────────┼───────────────────────────────┤  hair borders
    //   │  1    │  2026  │  MES         │  Câblage FS…                  │
    //   │  1    │  2026  │  MES         │  Switch Ubiquiti…             │  F5F5F5
    //   └──────────────────────────────────── 6 / 6 élément(s) vérifié(s) ┘  E8E8E8
    // ─────────────────────────────────────────────────────────────────────

    private function drawSection($section, $isFirst = false)
    {
        // Chaque organe démarre sur une nouvelle page (même s'il reste de la place).
        // Le 1er organe reste sous l'en-tête de la page 1 (sauf si la place y est trop juste).
        if (!$isFirst) {
            $this->AddPage();
        } elseif ($this->GetY() > 238) {
            $this->AddPage();
        }

        $col_widths = $this->calcColWidths($section['fields']);
        $x0 = self::ML;
        $w  = self::CW;

        // ── Section title bar ─────────────────────────────────────────────
        $y  = $this->GetY();
        $th = 9;
        $this->sf(self::TITLE_BG);
        $this->Rect($x0, $y, $w, $th, 'F');
        $this->SetFont('helvetica', 'B', 10);
        $this->sc(self::TITLE_FG);
        $this->SetXY($x0 + 4, $y);
        $this->Cell($w - 4, $th, 'ORGANE CONTRÔLÉ : ' . mb_strtoupper($this->t($section['label'])), 0, 1, 'L');
        // Border on title bar
        $this->sd(self::BDR_DARK);
        $this->SetLineWidth(0.5);
        $this->Rect($x0, $y, $w, $th, 'D');

        // White separator line between section title and column headers
        $this->SetDrawColor(255, 255, 255);
        $this->SetLineWidth(1.0);
        $this->Line($x0, $this->GetY(), $x0 + $w, $this->GetY());
        $this->SetLineWidth(0.2);

        // ── Column headers (navy bg + white text — exact Excel COLHDR style) ──
        $bottom_limit = $this->getPageHeight() - 18;
        $table_top    = $this->GetY();
        $this->drawColHeaders($section['fields'], $col_widths);
        $page_top = $table_top; // haut du tableau sur la page courante (pour la bordure)

        // ── Data rows ─────────────────────────────────────────────────────
        // Saut de page géré ici : on ferme la bordure de la page courante, on
        // passe à la page suivante et on répète les en-têtes de colonnes.
        foreach ($section['lines'] as $idx => $values) {
            $row_h = $this->calcRowHeight($values, $col_widths);
            if ($this->GetY() + $row_h > $bottom_limit) {
                $this->drawSectionBorder($x0, $page_top, $w, $this->GetY() - $page_top);
                $this->AddPage();
                $page_top = $this->GetY();
                $this->drawColHeaders($section['fields'], $col_widths);
            }
            $this->drawDataRow($values, $col_widths, ($idx % 2 === 1));
        }

        // ── Summary row ───────────────────────────────────────────────────
        $sum_h = 8;
        if ($this->GetY() + $sum_h > $bottom_limit) {
            $this->drawSectionBorder($x0, $page_top, $w, $this->GetY() - $page_top);
            $this->AddPage();
            $page_top = $this->GetY();
        }
        $y_sum   = $this->GetY();
        $sum_txt = $section['stats']['verified'] . ' / ' . $section['stats']['total'] . ' élément(s) vérifié(s)';
        $this->sf(self::SUMMARY_BG);
        $this->Rect($x0, $y_sum, $w, $sum_h, 'F');
        $this->SetFont('helvetica', 'B', 8.5);
        $this->sc(self::INFO_LABEL);
        $this->SetXY($x0, $y_sum);
        $this->Cell($w - 4, $sum_h, $sum_txt, 0, 1, 'R');

        // ── Bordure extérieure navy sur la (dernière) page du tableau ──
        $this->drawSectionBorder($x0, $page_top, $w, $this->GetY() - $page_top);

        $this->Ln(8);
    }

    // ─────────────────────────────────────────────────────────────────────
    // LEGENDE
    //
    // Regroupe en fin de rapport les légendes saisies sur chaque organe
    // (page de configuration de l'organe), pour expliciter les codes employés
    // dans les tableaux. Petit corps de texte, un bloc par organe concerné.
    //
    //   LÉGENDE
    //   EXTINCTEUR
    //     VR = Vérification annuelle
    //     NEUF = Ajout ou remplacement…
    // ─────────────────────────────────────────────────────────────────────

    const LG_LH   = 3.8;   // hauteur de ligne (mm)
    const LG_CODE = 30;    // largeur de la colonne des codes (mm)

    private function drawLegend($report_data)
    {
        global $langs;

        $groups = array();
        foreach ($report_data['sections'] as $section) {
            if (empty($section['legend'])) continue;
            $groups[] = array('label' => $section['label'], 'entries' => $section['legend']);
        }
        if (empty($groups)) return;

        $x0 = self::ML;
        $w  = self::CW;

        // Hauteur totale, pour garder le bloc d'un seul tenant si possible
        $this->SetFont('helvetica', '', 7);
        $needed = 6;
        foreach ($groups as $group) {
            $needed += 5;
            foreach ($group['entries'] as $entry) {
                $needed += $this->legendEntryHeight($entry, $w);
            }
            $needed += 2;
        }
        if ($this->GetY() + min($needed, 60) > $this->getPageHeight() - 20) {
            $this->AddPage();
        } else {
            $this->Ln(2);
        }

        // Titre
        $this->SetFont('helvetica', 'B', 8);
        $this->sc(self::INFO_LABEL);
        $this->SetXY($x0, $this->GetY());
        $this->Cell($w, 5, mb_strtoupper($this->t($langs->trans('gp_advexp_legend'))), 0, 1, 'L');
        $this->sd(self::BDR_DARK);
        $this->SetLineWidth(0.3);
        $this->Line($x0, $this->GetY(), $x0 + $w, $this->GetY());
        $this->Ln(1.5);

        foreach ($groups as $group) {
            if ($this->GetY() + 10 > $this->getPageHeight() - 20) $this->AddPage();

            $this->SetFont('helvetica', 'B', 7);
            $this->sc(self::INFO_LABEL);
            $this->SetXY($x0, $this->GetY());
            $this->Cell($w, 4, mb_strtoupper($this->t($group['label'])), 0, 1, 'L');

            foreach ($group['entries'] as $entry) {
                $h = $this->legendEntryHeight($entry, $w);
                if ($this->GetY() + $h > $this->getPageHeight() - 20) $this->AddPage();

                $y = $this->GetY();
                $code  = $this->t($entry['code']);
                $label = $this->t($entry['label']);

                if ($code !== '') {
                    $this->SetFont('helvetica', 'B', 7);
                    $this->sc(self::INFO_LABEL);
                    $this->SetXY($x0 + 2, $y);
                    $this->Cell(self::LG_CODE, self::LG_LH, $code, 0, 0, 'L');

                    $this->SetFont('helvetica', '', 7);
                    $this->sc(self::TEXT_MUTED);
                    $this->SetXY($x0 + 2 + self::LG_CODE, $y);
                    $this->MultiCell($w - 4 - self::LG_CODE, self::LG_LH, $label, 0, 'L', false, 1);
                } else {
                    $this->SetFont('helvetica', '', 7);
                    $this->sc(self::TEXT_MUTED);
                    $this->SetXY($x0 + 2, $y);
                    $this->MultiCell($w - 4, self::LG_LH, $label, 0, 'L', false, 1);
                }
            }

            $this->Ln(1.5);
        }
    }

    /** Hauteur d'une entrée de légende, calée sur le rendu de MultiCell. */
    private function legendEntryHeight($entry, $w)
    {
        $this->SetFont('helvetica', '', 7);
        $avail = ($entry['code'] !== '') ? ($w - 4 - self::LG_CODE) : ($w - 4);

        return max(1, $this->getNumLines($this->t($entry['label']), $avail)) * self::LG_LH;
    }

    // ─────────────────────────────────────────────────────────────────────
    // ANNEXE PHOTOGRAPHIQUE
    //
    //   ┌─ ANNEXE PHOTOGRAPHIQUE ───────────────────────────────────────┐  navy
    //   │ EXTINCTEUR                                                     │
    //   │ ┌────────┐ ┌────────┐ ┌────────┐                               │
    //   │ │ photo  │ │ photo  │ │ photo  │   3 vignettes par ligne       │
    //   │ └────────┘ └────────┘ └────────┘                               │
    //   │   N° 6       N° 7       N° 8                                   │
    //
    // Les clichés sont regroupés par organe, dans l'ordre des éléments.
    // ─────────────────────────────────────────────────────────────────────

    const PH_COLS   = 3;     // vignettes par ligne
    const PH_GAP    = 6;     // écart horizontal/vertical entre vignettes (mm)
    const PH_MAXH   = 46;    // hauteur max d'une vignette (mm)
    const PH_CAPH   = 5;     // hauteur du bandeau de légende (mm)

    private function drawPhotoAnnex($report_data)
    {
        global $langs;

        // Rassemble les photos par organe
        $groups = array();
        foreach ($report_data['sections'] as $section) {
            if (empty($section['photos'])) continue;
            $files = array();
            foreach ($section['photos'] as $photo) {
                $abs = GestionParcPhoto::getAbsolutePath($photo['path']);
                if (!file_exists($abs)) continue;
                $files[] = array('file' => $abs, 'item' => $photo['item']);
            }
            if (!empty($files)) $groups[] = array('label' => $section['label'], 'files' => $files);
        }
        if (empty($groups)) return;

        $this->AddPage();

        $x0 = self::ML;
        $w  = self::CW;

        // Titre de l'annexe
        $th = 9;
        $this->sf(self::TITLE_BG);
        $this->Rect($x0, $this->GetY(), $w, $th, 'F');
        $this->SetFont('helvetica', 'B', 11);
        $this->sc(self::TITLE_FG);
        $this->SetXY($x0, $this->GetY());
        $this->Cell($w, $th, mb_strtoupper($this->t($langs->trans('gp_advexp_photoannex'))), 0, 1, 'C');
        $this->Ln(4);

        $cell_w = ($w - (self::PH_COLS - 1) * self::PH_GAP) / self::PH_COLS;

        foreach ($groups as $group) {
            // Titre d'organe
            if ($this->GetY() + 14 > $this->getPageHeight() - 20) $this->AddPage();
            $this->SetFont('helvetica', 'B', 9);
            $this->sc(self::INFO_LABEL);
            $this->SetXY($x0, $this->GetY());
            $this->Cell($w, 6, mb_strtoupper($this->t($group['label'])), 0, 1, 'L');
            $this->sd(self::BDR_SOFT);
            $this->SetLineWidth(0.2);
            $this->Line($x0, $this->GetY(), $x0 + $w, $this->GetY());
            $this->Ln(2);

            $col = 0;
            $row_y = $this->GetY();
            $row_h = 0;

            foreach ($group['files'] as $photo) {
                $size = @getimagesize($photo['file']);
                if ($size === false || empty($size[0]) || empty($size[1])) continue;

                // Vignette contenue dans cell_w × PH_MAXH, ratio conservé
                $ratio = $size[0] / $size[1];
                $img_w = $cell_w;
                $img_h = $img_w / $ratio;
                if ($img_h > self::PH_MAXH) {
                    $img_h = self::PH_MAXH;
                    $img_w = $img_h * $ratio;
                }
                $cell_h = self::PH_MAXH + self::PH_CAPH;

                if ($col === 0) {
                    // Saut de page si la rangée ne tient pas
                    if ($row_y + $cell_h > $this->getPageHeight() - 20) {
                        $this->AddPage();
                        $row_y = $this->GetY();
                    }
                    $row_h = $cell_h;
                }

                $cx = $x0 + $col * ($cell_w + self::PH_GAP);
                // Image centrée horizontalement et calée en bas de la zone image
                $ix = $cx + ($cell_w - $img_w) / 2;
                $iy = $row_y + (self::PH_MAXH - $img_h) / 2;

                $this->Image($photo['file'], $ix, $iy, $img_w, $img_h, '', '', '', true, 300);
                $this->sd(self::BDR_SOFT);
                $this->SetLineWidth(0.2);
                $this->Rect($ix, $iy, $img_w, $img_h, 'D');

                // Légende : numéro de l'élément
                $this->SetFont('helvetica', '', 7.5);
                $this->sc(self::TEXT_MUTED);
                $this->SetXY($cx, $row_y + self::PH_MAXH);
                $this->Cell($cell_w, self::PH_CAPH, $this->t($photo['item']), 0, 0, 'C');

                $col++;
                if ($col >= self::PH_COLS) {
                    $col = 0;
                    $row_y += $row_h + self::PH_GAP;
                    $this->SetY($row_y);
                }
            }

            // Rangée incomplète : on descend quand même sous les vignettes
            if ($col > 0) {
                $row_y += $row_h + self::PH_GAP;
                $this->SetY($row_y);
            }

            $this->Ln(3);
        }
    }

    /** Dessine la bordure extérieure navy d'un bloc de tableau. */
    private function drawSectionBorder($x, $y, $w, $h)
    {
        if ($h <= 0) return;
        $this->sd(self::BDR_DARK);
        $this->SetLineWidth(0.55);
        $this->Rect($x, $y, $w, $h, 'D');
        $this->SetLineWidth(0.2);
    }

    /**
     * Hauteur d'une ligne de données, calée sur le rendu réel de MultiCell :
     * nb de lignes (au plus large) × hauteur de ligne ($line_h) + padding vertical.
     * On fixe la même police et le même padding que drawDataRow pour que
     * getNumLines() compte exactement comme MultiCell, évitant troncature et
     * espacements incohérents.
     */
    private function calcRowHeight($values, $col_widths)
    {
        $line_h = 5.0;   // hauteur d'une ligne rendue par MultiCell
        $min_h  = 7.0;   // hauteur minimale d'une ligne
        $vpad   = 3.0;   // padding vertical (1.5 haut + 1.5 bas)

        $this->SetFont('helvetica', '', 8.5);
        $this->setCellPaddings(3, 1.5, 2, 1.5); // identique au rendu

        $max_lines = 1;
        for ($c = 0; $c < count($values); $c++) {
            $n = $this->getNumLines($this->t((string) $values[$c]), $col_widths[$c]);
            if ($n > $max_lines) $max_lines = $n;
        }

        return max($min_h, $max_lines * $line_h + $vpad);
    }

    // ── Column headers — navy bg, white text, white internal dividers ─────

    private function drawColHeaders($fields, $col_widths)
    {
        $x0 = self::ML;
        $h  = 8.5;
        $y  = $this->GetY();

        $this->sf(self::TITLE_BG);
        $this->Rect($x0, $y, self::CW, $h, 'F');

        $this->SetFont('helvetica', 'B', 8.5);
        $this->sc(self::TITLE_FG);
        // White dividers between columns (matching Excel white borders on col headers)
        $this->SetDrawColor(255, 255, 255);
        $this->SetLineWidth(0.3);

        $this->SetXY($x0, $y);
        $i = 0;
        foreach ($fields as $fdata) {
            $border = ($i > 0) ? 'L' : '0';
            $this->Cell($col_widths[$i], $h, $this->t($fdata['label']), $border, 0, 'C', true);
            $i++;
        }
        $this->Ln($h);

        $this->SetLineWidth(0.2);
        $this->sd(self::BDR_SOFT);
    }

    // ── Data row — alternating bg, hair borders (Excel BORDER_HAIR style) ─

    private function drawDataRow($values, $col_widths, $isOdd)
    {
        $line_h = 5.0;  // height of ONE line passed to MultiCell

        $this->SetFont('helvetica', '', 8.5);

        // Hauteur de la ligne (le saut de page est géré en amont par drawSection)
        $row_h = $this->calcRowHeight($values, $col_widths);

        $y  = $this->GetY();
        $x0 = self::ML;
        $w  = self::CW;

        // Row fill (uses total row height)
        $this->sf($isOdd ? self::ROW_ODD : self::ROW_EVEN);
        $this->Rect($x0, $y, $w, $row_h, 'F');

        $this->sc(self::INFO_VALUE);
        $this->sd(self::BDR_SOFT);
        $this->SetLineWidth(0.12);
        $this->setCellPaddings(3, 1.5, 2, 1.5);
        $this->SetXY($x0, $y);

        for ($c = 0; $c < count($values); $c++) {
            $val   = $this->t((string)$values[$c]);
            $clean = trim($val);
            $align = (is_numeric($clean) || preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $clean)) ? 'C' : 'L';
            // $line_h = height of each rendered line ; $row_h = maxh clips overflow
            $this->MultiCell($col_widths[$c], $line_h, $val, 'B', $align, true, 0, '', '', true, 0, false, true, $row_h);
        }

        // Explicitly reposition to the end of this row (all cells share the same row height)
        $this->SetXY($x0, $y + $row_h);

        $this->setCellPaddings(1, 1, 1, 1);
        $this->SetLineWidth(0.2);
    }

    // ── Column width calculator ───────────────────────────────────────────

    private function calcColWidths($fields)
    {
        if (empty($fields)) return array();

        $weights = array();
        foreach ($fields as $fdata) {
            $lbl = mb_strtolower($this->t($fdata['label']));
            $w   = 1.0;
            if (
                strpos($lbl, 'produit')     !== false
                || strpos($lbl, 'product')     !== false
                || strpos($lbl, 'désignation') !== false
                || strpos($lbl, 'designation') !== false
                || strpos($lbl, 'libellé')     !== false
            ) {
                $w = 2.2;
            } elseif (isset($fdata['type']) && $fdata['type'] === 'date') {
                $w = 0.75;
            } elseif (preg_match('/num|ref|n°|id\b|série|serie|s\/n/i', $lbl)) {
                $w = 0.8;
            } elseif (mb_strlen($fdata['label']) > 18) {
                $w = 1.3;
            }
            $weights[] = $w;
        }

        $sum = array_sum($weights);
        return array_map(function ($wt) use ($sum) {
            return round(($wt / $sum) * self::CW, 2);
        }, $weights);
    }
}

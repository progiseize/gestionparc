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

require_once DOL_DOCUMENT_ROOT.'/core/modules/export/modules_export.php';
include_once DOL_DOCUMENT_ROOT.'/core/modules/export/export_excel2007.modules.php';

use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;

/**
 *	Class to build export files with format CSV
 */
class GestionParcExport extends ExportExcel2007
{

	public function customHeader($row_start, $nb_rows, $customer, $parclabel = ''){

		global $mysoc, $user, $conf, $langs;

		$row = $row_start;
		$sheet = $this->workbook->getActiveSheet();
        $letters_array = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];

        //var_dump($nb_rows);
        $e_key = 4; // array_search('E', $letters_array);
        $lastcol_letterkey = $e_key;
        if($nb_rows > ($e_key + 1)):
            $lastcol_letterkey = $nb_rows - 1;
        endif;

		$sheet->mergeCells('A'.$row.':'.$letters_array[$lastcol_letterkey].$row);
        $sheet->setCellValue('A'.$row,$langs->transnoentities('gp_advexp_title').' '.date('Y'));
        $sheet->getStyle('A'.$row.':'.$letters_array[$lastcol_letterkey].$row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A'.$row.':'.$letters_array[$lastcol_letterkey].$row)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
        $sheet->getStyle('A'.$row.':'.$letters_array[$lastcol_letterkey].$row)->getFont()->setBold(true);
        $row++;

        //
        $lastrowforlogo = $row+4;
        $sheet->mergeCells('A'.$row.':B'.$lastrowforlogo.'');
        if(!empty($mysoc->logo) && is_readable($conf->mycompany->dir_output.'/logos/'.$mysoc->logo)):
	        $this->addImage('A'.$row, $conf->mycompany->dir_output.'/logos/'.$mysoc->logo, 'Logo', 'Logo');
	        $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    	endif;

        $lastrowforlogo++;
        $lastrowforlogo++;
        $sheet->setCellValue('A'.$lastrowforlogo,$mysoc->nom);
        $sheet->getStyle('A'.$lastrowforlogo)->getFont()->setBold(true);
        $sheet->mergeCells('A'.$lastrowforlogo.':B'.$lastrowforlogo.'');
        $sheet->getStyle('A'.$lastrowforlogo)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $lastrowforlogo++;
        $sheet->setCellValue('A'.$lastrowforlogo,$mysoc->address);
        $sheet->mergeCells('A'.$lastrowforlogo.':B'.$lastrowforlogo.'');
        $sheet->getStyle('A'.$lastrowforlogo)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $lastrowforlogo++;
        $sheet->setCellValue('A'.$lastrowforlogo,$mysoc->zip.' '.$mysoc->town);
        $sheet->mergeCells('A'.$lastrowforlogo.':B'.$lastrowforlogo.'');
        $sheet->getStyle('A'.$lastrowforlogo)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $lastrowforlogo++;

        $contact_array = array();
        if(isset($mysoc->phone) && !empty($mysoc->phone)):
            $contact_array[] = trim(wordwrap($mysoc->phone,2,'.',TRUE));
        endif;
        if(isset($mysoc->email) && !empty($mysoc->email)):
            $contact_array[] = trim($mysoc->email);
        endif;

        $contact_text = implode(' - ', $contact_array);
        $sheet->setCellValue('A'.$lastrowforlogo,$contact_text);
        $sheet->mergeCells('A'.$lastrowforlogo.':B'.$lastrowforlogo.'');
        $sheet->getStyle('A'.$lastrowforlogo)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $row++;

        //
        $date_intervention = dol_stringtotime(date('Y-m-d'));
        $sheet->mergeCells('C'.$row.':D'.$row.'');
        $sheet->setCellValue('C'.$row, $langs->transnoentities('gp_advexp_datevisit'));
        $sheet->setCellValue('E'.$row, \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($date_intervention));
        $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle('C'.$row.':'.$letters_array[$lastcol_letterkey].$row)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('C'.$row)->getFont()->setBold(true);
        $sheet->getStyle('E'.$row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        if($lastcol_letterkey > $e_key):
            $sheet->mergeCells('E'.$row.':'.$letters_array[$lastcol_letterkey].$row.'');
        endif;
        $row++;

        $sheet->mergeCells('C'.$row.':D'.$row.'');
        $sheet->setCellValue('C'.$row, $langs->transnoentities('gp_advexp_user'));
        $sheet->setCellValue('E'.$row, $user->firstname.' '.$user->lastname);
        $sheet->getStyle('C'.$row.':'.$letters_array[$lastcol_letterkey].$row)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('C'.$row)->getFont()->setBold(true);
        $sheet->getStyle('E'.$row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        if($lastcol_letterkey > $e_key):
            $sheet->mergeCells('E'.$row.':'.$letters_array[$lastcol_letterkey].$row.'');
        endif;
        $row++;

        $salesrepresentatives = $customer->getSalesRepresentatives($user);
        $salesrepresentatives_label = '';
        if(!empty($salesrepresentatives)):
        	$i = 0;
        	foreach ($salesrepresentatives as $salesrep): $i++;
        		if($i != 1): $salesrepresentatives_label .= ' / '; endif;
        		$salesrepresentatives_label .= $salesrep['firstname'].' '.$salesrep['lastname'];
        	endforeach;
        endif;
        $sheet->mergeCells('C'.$row.':D'.$row.'');
        $sheet->setCellValue('C'.$row, $langs->transnoentities('gp_advexp_commercial'));
        $sheet->setCellValue('E'.$row, $salesrepresentatives_label);
        $sheet->getStyle('C'.$row.':'.$letters_array[$lastcol_letterkey].$row)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('C'.$row)->getFont()->setBold(true);
        $sheet->getStyle('E'.$row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        if($lastcol_letterkey > $e_key):
            $sheet->mergeCells('E'.$row.':'.$letters_array[$lastcol_letterkey].$row.'');
        endif;
        $row++;

        $row++;

        //
        $sheet->setCellValue('C'.$row, $langs->transnoentities('Customer'));
        $sheet->setCellValue('D'.$row, $customer->nom);
        $sheet->mergeCells('D'.$row.':'.$letters_array[$lastcol_letterkey].$row.'');
        $sheet->getStyle('C'.$row.':'.$letters_array[$lastcol_letterkey].$row)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('C'.$row)->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue('C'.$row, $langs->transnoentities('Address'));
        $sheet->setCellValue('D'.$row, $customer->address.', '.$customer->zip.' '.$customer->town);
        $sheet->mergeCells('D'.$row.':'.$letters_array[$lastcol_letterkey].$row.'');
        $sheet->getStyle('C'.$row.':'.$letters_array[$lastcol_letterkey].$row)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('C'.$row)->getFont()->setBold(true);
        $row++;

        //
        $phone = !empty($customer->phone) ? wordwrap($customer->phone,2,'.',TRUE) : '';
        $sheet->setCellValue('C'.$row, $langs->transnoentities('PhoneNumber'));
        $sheet->setCellValue('D'.$row, $phone);
        $sheet->getStyle('C'.$row.':D'.$row)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('C'.$row)->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue('C'.$row, $langs->transnoentities('CustomerCode'));
        $sheet->setCellValue('D'.$row, $customer->code_client);
        $sheet->getStyle('C'.$row.':D'.$row)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('C'.$row)->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue('C'.$row, $langs->transnoentities('gp_advexp_parctype'));
        $sheet->setCellValue('D'.$row, $parclabel);
        $sheet->getStyle('C'.$row.':D'.$row)->getBorders()->getOutline()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $sheet->getStyle('C'.$row)->getFont()->setBold(true);
        $row++;

        return $row;
	}

	public function addImage($coords, $image_path, $image_name = '', $image_description = ''){

		$sheet = $this->workbook->getActiveSheet();

		$drawing = new Drawing();
		$drawing->setName($image_name);
		$drawing->setDescription($image_description);
		$drawing->setPath($image_path);
		$drawing->setHeight(100);
		$drawing->setCoordinates($coords);

		$imageWidth = $drawing->getWidth() / 2;
		$drawing->setOffsetX($imageWidth);
		$drawing->setOffsetY(10);
		$drawing->setWorksheet($sheet);
	}

}
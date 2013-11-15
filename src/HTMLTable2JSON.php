<?php
/*****************************************************************
* HTMLTable2JSON.php
* Updated: Tuesday, Nov. 12, 2013
* 
* Colin Tremblay
* Grinnell College '14
*
* Creates a JSON file from the first HTML table at the given URL
******************************************************************/

ini_set('display_errors', 'On');
ini_set('memory_limit', '-1');
include_once "TableColumn.php";
include_once "TableRow.php";

class HTMLTable2JSON {

	public function tableToJSON($url, $firstColIsRowName = TRUE, $tableID = '', $ignoreCols = NULL, $headers = NULL, $firstRowIsData = FALSE, $testing = NULL) {
		$ignoring = FALSE;
		if (NULL != $ignoreCols) {
			if (!is_array($ignoreCols)) {
				echo('ignoreCols must be an array. Did not ignore any columns.<br />');
				$ignoreCols = NULL;
			}
			else  
				for ($i = 0; $i < count($ignoreCols); $i++) {
					if(is_int($ignoreCols[0])) {
						$ignoring = TRUE;
						break;
					}
				}
		}
		if (NULL != $headers)
			if (!is_array($headers)) {
				echo('headers must be an array. Will not change any headers.<br />');
				$headers = NULL;
			}

		if (NULL == $testing) {
			// Get html using curl
			$c = curl_init($url);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			$html = curl_exec($c);
			if (curl_error($c))
				die(curl_error($c));

			// Check return status
			$status = curl_getinfo($c, CURLINFO_HTTP_CODE);
			if (200 <= $status && 300 > $status)
				echo 'Got the html from '.$url.'<br />';
			else
				die('Failed to get html from '.$url.'<br />');
			curl_close($c);
		}
		else $html = $testing;

		// Pull table out of HTML
		$table_str = '<table id="'.$tableID;
		$start_pos = stripos($html, $table_str);
		$end_pos = stripos($html, '</table>', $start_pos) + strlen('</table>');
		$length = $end_pos - $start_pos;
		$table = substr($html, $start_pos, $length);
		$permanent_table = $table;

		// Set up arrays
		$column_array = array();
		$row_array = array();
		$header_start = stripos($table, '<th');
		if (false !== $header_start) {
			$header_end = stripos($table, '<thead>');
			if (false !== $header_end)
				$header_start = stripos($table, '<th', $header_end + 1);

			$header_end = stripos($table, '</tr>');
			$header_end += strlen('</tr>');
			$header_length = $header_end - $header_start;
			$header = substr($table, $header_start, $header_length);

			for ($i = 0; false !== $header_start; $i++) {
				$header_start = stripos($header, '>') + strlen('>');
				$header_end = stripos($header, '</th>', $header_start);
				if ($header_end != $header_start) {
					$header_length = $header_end - $header_start;
					$cell_name = substr($header, $header_start, $header_length);
					$cell_name = str_replace('"', '\"', $cell_name);
					$cell_name = str_replace('<br />', '', $cell_name);
					$cell_name = trim($cell_name);
					array_push($column_array, new TableColumn($cell_name));
				}
				else 
					array_push($column_array, new TableColumn('Column'.$i));
	
				// Cut out 
				$header_end = stripos($header, '</tr>');
				$header_end += strlen('</tr>');
				$header_start = stripos($header, '</th>');
				$header_start += strlen('</th>');
				$header_length = $header_end - $header_start;
				$header = substr($header, $header_start, $header_length);

				// set up next pass through loop
				$header_start = stripos($header, '<th');
			}
		
			// Trim out the header row 
			$start_pos = stripos($table, '</th>') + strlen('</th>');
		}
		else 
			$start_pos = stripos($table, '<tr');
		
		$table = substr($table, $start_pos, $length - $start_pos);

		if (NULL != $headers)
			foreach ($headers as $key => $value) {
				$column_array[$key]->setName($value);
			}
		// Set up array for skipping columns that don't show up in HTML
			// due to rowspan > 1 in the previous row
		$skipped_columns = array();

		// Loop through the rows
		$start_pos = stripos($table, '<tr');
		for ($j = 0; false !== $start_pos; $j++) {
			// If this row doens't have a skipped array, add one
			if (count($skipped_columns) <= $j + 1) {
				$row_with_skipped_columns = array();
				array_push($skipped_columns, $row_with_skipped_columns);
			}

			// Create temp string with JUST the row we're currently looking at
			$end_pos = stripos($table, '</tr>', $start_pos); //HERE??
			$end_pos += strlen('</tr>');
			$length = $end_pos - $start_pos;
			$temp = substr($table, $start_pos, $length);

			$table_row_object = new TableRow();
			if ($firstColIsRowName){
				// Get Header from column 1 and trim out column 1
				$inner_pos_start = stripos($temp, '<td');
				$inner_pos_start = stripos($temp, '>', $inner_pos_start) + strlen('>');
				$inner_pos_end = stripos($temp, '</td>');
				$inner_len = $inner_pos_end - $inner_pos_start;
				$row_header = substr($temp, $inner_pos_start, $inner_len);
				$row_header = str_replace('<br />', '', $row_header);
				$row_header = trim($row_header);
				
				$inner_pos_start = $inner_pos_end + strlen('</td>');
				$inner_pos_end = stripos($temp, '</tr>') + strlen('</tr>');
				$inner_len = $inner_pos_end - $inner_pos_start;
				$temp = substr($temp, $inner_pos_start, $inner_len);
				$i = 1;
			}
			else {
				$row_header = 'Row '.$j;
				$i = 0;
			}

			// Loop through the columns
			$inner_pos_start = stripos($temp, '<td');
			for ($i; false !== $inner_pos_start; $i++) {
				// Skip over columns in the array created by rowspans
				while (in_array($i, $skipped_columns[$j]))
					$i++;

				// Skip over user specified columns
				if (NULL == $ignoreCols || !in_array($i, $ignoreCols)) {
					$inner_pos_start += strlen('<td');
					$inner_pos_end = stripos($temp, '</td>', $inner_pos_start) + strlen('</td>');
					$inner_len = $inner_pos_end - $inner_pos_start;
					$cell = substr($temp, $inner_pos_start, $inner_len);

					$inner_pos_end = stripos($cell, '</td>');
					$inner_pos_start = stripos($cell, '>') + strlen('>');
					if ($inner_pos_end != $inner_pos_start) {
						$inner_len = $inner_pos_end - $inner_pos_start;
						$cell_name = substr($cell, $inner_pos_start, $inner_len);
						$cell_name = str_replace('"', '\"', $cell_name);
						$cell_name = str_replace('<br />', '', $cell_name);
						$cell_name = trim($cell_name);

						$other_pos = stripos($cell, ' rowspan-');
						if (false === $other_pos)
							$spans_one = true;
						else $spans_one = false;

						if(!$spans_one) {
							$inner_pos_start = stripos($cell, ' rowspan-') + strlen(' rowspan-');
							$inner_pos_end = stripos($cell, '">');
							$inner_len = $inner_pos_end - $inner_pos_start;
							$span = substr($cell, $inner_pos_start, $inner_len);
							$span_number = intval($span);
							$span_no = $span_number;
							// Set up skipped columns to skip over columns missed in HTML in future rows due to rowspan > 1
							for ($k = $j + 1; $span_no > 1; $span_no--, $k++) {
								if (count($skipped_columns) <= $k) {
									$another_row_with_skipped_columns = array();
									array_push($skipped_columns, $another_row_with_skipped_columns);
								}
								array_push($skipped_columns[$k], $i);
							}
						}
						else $span_number = 1;
						$column_array[$i]->addCell($cell_name, $row_header, $span_number);
					
						if(!$firstColIsRowName){
							$column_header = $column_array[$i]->getName();
							$table_row_object->addAttributePair($column_header, $cell_name);
						}
					}
				}
				// Cut out 
				$inner_pos_end = stripos($temp, '</td>') + strlen('</td>');
				$inner_pos_start = stripos($temp, '</tr>') + strlen('</tr>');
				$inner_len = $inner_pos_start - $inner_pos_end;
				$temp = substr($temp, $inner_pos_end, $inner_len);

				// set up next pass through loop
				$inner_pos_start = stripos($temp, '<td');
			}
			if (!$firstColIsRowName) {
				array_push($row_array, $table_row_object);
			}
			$start_pos = stripos($table, '</table');
			$start_pos += strlen('</table');
			$length = $start_pos - $end_pos;
			$table = substr($table, $end_pos, $length);
			$start_pos = stripos($table, '<tr');
		}

		$outfile = 'output.json';
		if (false == ($out_handle = fopen($outfile, 'w')))
			die('Failed to create output file.');

		if($firstColIsRowName) {
			$output = "{";
			foreach($column_array as &$col) {
				if ($col->hasCells())
					$output = $output.$col->writeJSON().",\n";
			}
			$output = trim($output, ",\n");
			$output = $output."\n}";
		}
		else {
			$output = "[";
			foreach($row_array as &$row) {
				$output = $output.$row->writeJSON().",\n";
			}
			$output = trim($output, ",\n");
			$output = $output."\n]";
		}
		if (NULL == $testing) {
			fwrite($out_handle, $output);
			fclose($out_handle);
			echo 'JSON Created<br />';
		}
		else {
			$output = str_replace("\n", "", $output);
			$output = str_replace("\t", "", $output);
			return $output;
		}
	}
}
?>

<?php

	session_cache_limiter('none');
	session_start();

	include("../common.php");
	require_once('../class/validator.class.php');
		
	//------------------------------------------------------------------------------------------------------------------
	// Import $_POST variables and validation
	//------------------------------------------------------------------------------------------------------------------
	$params = array();
	$validator = new FormValidator();
	
	$validator->addRules(array(
		"execID" => array(
			"type" => "int",
			"required" => 1,
			"min" => 1,
			"errorMes" => "[ERROR] CAD ID is invalid."),
		"cadName" => array(
			"type" => "string",
			"regex" => "/^[\w-_]+$/",
			"required" => 1,
			"errorMes" => "'CAD name' is invalid."),
		"version" => array(
			"type" => "string",
			"regex" => "/^[\w-_\.]+$/",
			"required" => 1,
			"errorMes" => "'Version' is invalid."),			
		"feedbackMode" => array(
			"type" => "select",
			"required" => 1,
			"options" => array("personal", "consensual"),
			"errorMes" => "[ERROR] 'Feedback mode' is invalid."),
		"interruptFlg" => array(
			"type" => "select",
			"required" => 1,
			"options" => array("0", "1"),
			"errorMes" => "[ERROR] 'interruptFlg' is invalid."),
		"fnFoundFlg" => array(
			"type" => "select",
			"required" => 1,
			"options" => array("0", "1"),
			"otherwise" => "1"),
		"candStr" => array(
			"type" => "string",
			"required" => 1,
			"regex" => "/^[\d\^]+$/",
			"errorMes" => "[ERROR] 'Candidate string' is invalid."),
		"evalStr" => array(
			"type" => "string",
			"required" => 1,
			"regex" => "/^[\d-\^]+$/",
			"errorMes" => "[ERROR] 'Evaluation string' is invalid.")
		));				

	if($validator->validate($_POST))
	{
		$params = $validator->output;
		$params['errorMessage'] = "";
	}
	else
	{
		$params = $validator->output;
		$params['errorMessage'] = implode('<br/>', $validator->errors);
	}

	$params['toTopDir'] = '../';
	$userID = $_SESSION['userID'];
	//------------------------------------------------------------------------------------------------------------------

	$dstData = array('message'      => $params['errorMessage'],
					 'interruptFlg' => $params['interruptFlg']);

	try
	{
		// Connect to SQL Server
		$pdo = new PDO($connStrPDO);	
	
		$registeredAt = date('Y-m-d H:i:s');
		$consensualFlg = ($params['feedbackMode'] == "consensual") ? 't' : 'f';

		$stmt = $pdo->prepare("SELECT result_type, score_table FROM cad_master WHERE cad_name=? AND version=?");
		$stmt->execute(array($params['cadName'], $params['version']));

		$result = $stmt->fetch(PDO::FETCH_NUM);
		$resultType     = $result[0];
		$scoreTableName = $result[1];
		
		if($resultType == 1)
		{
			$candArr = explode("^", $params['candStr']);
			$evalArr = explode('^', $params['evalStr']);
			
			$candNum = count($candArr);
			
			//------------------------------------------------------------------------------------------------
			// Registration to lesion_feedback table
			//------------------------------------------------------------------------------------------------
			$sqlStr = "DELETE FROM lesion_feedback WHERE exec_id=? AND consensual_flg=?";
			if($params['feedbackMode'] == "personal") $sqlStr .= " AND entered_by=?";
			
			$stmt = $pdo->prepare($sqlStr);
			$stmt->bindParam(1, $params['execID']);
			$stmt->bindParam(2, $consensualFlg);
			if($params['feedbackMode'] == "personal")   $stmt->bindParam(3, $userID);
			$stmt->execute();

			for($i=0; $i<($candNum-1); $i++)
			{
				$sqlStr = "INSERT INTO lesion_feedback (exec_id, lesion_id, entered_by, consensual_flg, "
					        . "evaluation, interrupt_flg, registered_at) VALUES (?, ?, ?, ?, ?, ?, ?);";
						
				$sqlParams[0] = $params['execID'];
				$sqlParams[1] = $candArr[$i];
				$sqlParams[2] = $userID;
				$sqlParams[3] = $consensualFlg;
				$sqlParams[4] = $evalArr[$i];
				$sqlParams[5] = ($params['interruptFlg']) ? "t" : "f";
				$sqlParams[6] = $registeredAt;

				$stmt = $pdo->prepare($sqlStr);
				$stmt->execute($sqlParams);
				
				if($stmt->rowCount() != 1)
				{
					$err = $stmt->errorInfo();
					$dstData['message'] .= $err[2];
					break;
				}
			}
			//----------------------------------------------------------------------------------------------------
	
			//----------------------------------------------------------------------------------------------------
			// Registration to false_negative_count table
			//----------------------------------------------------------------------------------------------------
			if($dstData['message'] == "")
			{
				$status = ($params['interruptFlg']) ? 1 : 2;

				$sqlStr = "SELECT * FROM false_negative_count WHERE exec_id=? AND consensual_flg=?";
				if($params['feedbackMode'] == "personal") $sqlStr .= " AND entered_by=?";
		
				$stmt = $pdo->prepare($sqlStr);
				$stmt->bindValue(1, $params['execID']);
				$stmt->bindValue(2, $consensualFlg, PDO::PARAM_BOOL);
				if($params['feedbackMode'] == "personal")   $stmt->bindValue(3, $userID);

				$stmt->execute();
				$rowNum = $stmt->rowCount();
				$result = $stmt->fetch(PDO::FETCH_ASSOC);
				
				$sqlParams = array();

				if($rowNum == 0 && !$params['fnFoundFlg'])
				{
					$sqlStr = "INSERT INTO false_negative_count "
					        . "(exec_id, entered_by, consensual_flg, false_negative_num, status, registered_at)"
					        . " VALUES (?, ?, ?, 0, ?, ?);";
					$sqlParams[] = $params['execID'];
					$sqlParams[] = $userID;
					$sqlParams[] = $consensualFlg;
					$sqlParams[] = $status;
					$sqlParams[] = $registeredAt;
					
					$stmtFN = $pdo->prepare($sqlStr);
					$stmtFN->execute($sqlParams);

					if($stmtFN->rowCount() != 1) $dstData['message'] .= "Fail to save the number of FN.";
				}
				else if($rowNum == 1)
				{
					$savedFnNum = $result['false_negative_num'];
					$savedStatus= $result['status'];
					
					if($savedFnNum == 0)
					{
						if($savedStatus != $status)
						{
							$sqlStr = "UPDATE false_negative_count SET status=?, registered_at=?";
							$sqlParams[] = $status;
							$sqlParams[] = $registeredAt;
					
							if($params['feedbackMode'] == "consensual")
							{
								$sqlStr .= ", entered_by=?";
								$sqlParams[] = $userID;
							}
				
							$sqlStr .= " WHERE exec_id=? AND consensual_flg=?";
							$sqlParams[] = $params['execID'];
							$sqlParams[] = $consensualFlg;
				
							if($params['feedbackMode'] == "personal")
							{
								$sqlStr .= " AND entered_by=?";
								$sqlParams[] = $userID;
							}

							$stmt = $pdo->prepare($sqlStr);
							$stmt->execute($sqlParams);
							
							if($stmt->rowCount() != 1) $dstData['message'] .= "Fail to update FN table.";
						}
					}
					else
					{
					 	if($savedStatus != $status)
					 	{						
							$sqlStr = "UPDATE false_negative_count SET status=?, registered_at=?";
							$sqlParams[] = $status;
							$sqlParams[] = $registeredAt;
				
							if($params['feedbackMode'] == "consensual")
							{
								$sqlStr .= ", entered_by=?";
								$sqlParams[] = $userID;
							}
							
							$sqlStr .= " WHERE exec_id=? AND consensual_flg=?";
							$sqlParams[] = $params['execID'];
							$sqlParams[] = $consensualFlg;
							
							if($params['feedbackMode'] == "personal")
							{
								$sqlStr .= " AND entered_by=?";
								$sqlParams[] = $userID;
							}
			
							$stmt = $pdo->prepare($sqlStr);
							$stmt->execute($sqlParams);

							if($stmt->rowCount() != 1) 	$dstData['message'] .= "Fail to update FN table.";
			
							if($dstData['message'] == "")
							{
								$sqlParams = array();
					
								$sqlStr = "UPDATE false_negative_location SET interrupt_flg=?,"
								        . " registered_at=?";
								$sqlParams[] = ($params['interruptFlg']) ? 't' : 'f';
								$sqlParams[] = $registeredAt;				
				
								if($params['feedbackMode'] == "consensual")
								{
									$sqlStr .= ", entered_by=?";
									$sqlParams[] = $userID;
								}
				
								$sqlStr .= " WHERE exec_id=? AND consensual_flg=?";
								$sqlParams[] = $params['execID'];
								$sqlParams[] = $consensualFlg;
				
								if($params['feedbackMode'] == "personal")
								{
									$sqlStr .= " AND entered_by=?";
									$sqlParams[] = $userID;
								}
				
								$stmt = $pdo->prepare($sqlStr);
								$stmt->execute($sqlParams);

								if($stmt->rowCount() != $result['false_negative_num'])
								{
									$dstData['message'] .= "Fail to update FN table.";
								}
							}
						}  
					}
				}
			}
			//----------------------------------------------------------------------------------------------------------

			if($dstData['message'] == "" && $params['interruptFlg'] == 0)
			{
				$dstData['message'] .= 'Successfully registered in feedback database.';
			}
		}
		else if($resultType == 2)  // 未修正(2010.11.5)
		{
			$scoreTableName = ($scoreTableName !== "") ? $scoreTableName : "visual_assessment";
		
			$sqlStr = "SELECT interrupt_flg FROM \"" . $scoreTableName . "\" WHERE exec_id=?"
					. " AND consensual_flg=?";
						
			if($params['feedbackMode'] == "personal") $sqlStr .= " AND entered_by=?";
			
			$stmt = $pdo->prepare($sqlStr);
			$stmt->bindValue(1, $params['execID']);
			$stmt->bindValue(2, $consensualFlg, PDO::PARAM_BOOL);
			if($feedbackMode == "personal")  $stmt->bindValue(3, $userID);
			
			$stmt->execute();
			$rowNum = $stmt->rowCount();
			
			$sqlStr = "";
			$sqlParams = array();		
			
			if($scoreTableName == "visual_assessment")
			{
				if($rowNum == 0)
				{
					$sqlStr = "INSERT INTO visual_assessment"
					        . " (exec_id, entered_by, consensual_flg, interrupt_flg, score, registered_at)"
							. " VALUES (?, ?, ?, ?, ?, ?);";
					$sqlParams[] = $params['execID'];
					$sqlParams[] = $userID;
					$sqlParams[] = $consensualFlg;
					$sqlParams[] = ($params['interruptFlg']) ? "t" : "f";
					$sqlParams[] = $evalStr;
					$sqlParams[] = $registeredAt;						
				}
				else if($rowNum == 1 && $stmt->fetchColumn() == 't')
				{
					$sqlStr = "UPDATE visual_assessment SET score=?, registered_at=?";
					$sqlParams[] = $evalStr;
					$sqlParams[] = $registeredAt;
	
					if($params['interruptFlg'] == 0)
					{
						$sqlStr .= ", interrupt_flg='f'";
					}
					
					if($params['feedbackMode'] == "consensual")
					{
						$sqlStr .= ", entered_by=?";
						$sqlParams[] = $userID;
					}
	
					$sqlStr .= " WHERE exec_id=? AND consensual_flg=?";
					$sqlParams[] = $params['execID'];
					$sqlParams[] = $consensualFlg;				
					
					if($params['feedbackMode'] == "personal")
					{
						$sqlStr .= " AND entered_by=?";
						$sqlParams[] = $userID;					
					}	
				}
			}
			else
			{
				$tmpArr = explode("^", $params['evalStr']);
	
				// カラム名の取得
				$sqlStr = "SELECT attname FROM pg_attribute WHERE attnum > 3"
				        . " AND attrelid = (SELECT relfilenode FROM pg_class WHERE relname='".$scoreTableName."')"
						. " AND attname != 'registered_at' AND attname != 'interrupt_flg' ORDER BY attnum";
						
				$stmtCol = $pdo->prepare();
				$stmtCol->execute();
				$colNum = $stmtCol->rowCount();
				
				if($rowNum == 0)
				{
					$sqlStr = "INSERT INTO \"" . $scoreTableName . "\""
					        . " (exec_id, entered_by, consensual_flg, interrupt_flg,";	
				
					while($resultCol = $stmtCol->fetch(PDO::FETCH_NUM)) 
					{
						$sqlStr .= $colRow[0] . ', ';
					}
			
					$sqlParams = array();
			
					$sqlStr .= " registered_at) VALUES (?, ?, ?, ?,";
					$sqlParams[0] = $params['execID'];
					$sqlParams[1] = $userID;
					$sqlParams[2] = $consensualFlg;
					$sqlParams[3] = ($params['interruptFlg']) ? "t" : "f";
							
					for($i=0; $i<$colNum; $i++)
					{
						$sqlStr .= "?,";
						$sqlParams[] = $tmpArr[$i];
					}
						
					$sqlStr .= "'?)";
					$sqlParams[] = $registeredAt;
				}
				//else if($rowNum == 1 && $stmt->fetchColumn() == 't')
				else if($rowNum == 1 && ($stmt->fetchColumn() == 't' || $params['interruptFlg']))
				{
					$sqlStr = "UPDATE \"" . $scoreTableName . "\" SET ";
					
					for($i=0; $i<$colNum; $i++)
					{
						$resultCol = $stmtCol->fetch(PDO::FETCH_NUM);
						$sqlStr .= $colRow[0] . '=?, ';
						$sqlParams[$i] = $tmpArr[$i];
					}				
					
					$sqlStr .= " registered_at=?";
					$sqlParams[] = $registeredAt;
					
					if($params['interruptFlg'] == 0)  $sqlStr .= ", interrupt_flg='f'";
					
					if($params['feedbackMode'] == "consensual")
					{
						$sqlStr .= ", entered_by=?";
						$sqlParams[] = $userID;	
					}
					
					$sqlStr .= " WHERE exec_id=? AND consensual_flg=?";
					$sqlParam[] = $execID;
					$sqlParam[] = $consensualFlg;
						
					if($params['feedbackMode'] == "personal")
					{
						$sqlStr .= " AND entered_by=?";
						$sqlParam[] = $userID;
					}		
				}
				//echo $sqlStr;
			}
			
			$stmt = $pdo->prepare($sqlStr);
			$stmt->execute($sqlParam);
		
			if($stmt->rowCount() == 1)
			{
				$dstData['message'] = 'Successfully registered in feedback database.';
			}
			else
			{
				$tmp = $stmt->errorInfo();
				$dstData['message'] = $tmp[2];
			}
		}
		echo json_encode($dstData);
	}
	catch (PDOException $e)
	{
		var_dump($e->getMessage());
	}
	$pdo = null;

?>
<?php

$app->get('/', function ($request, $response, $args) {
	$errors = [];
	$flash = $this->flash->getMessages();
	if (array_key_exists('error', $flash))
		$errors = $flash['error'];

	return $this->renderer->render($response, 'index.phtml', ['erreurs' => $errors, 'router' => $this->router]);
})->setName('home');


$app->post('/notes', function ($request, $response, $args) {
	$settings = $this->get('settings')['ent'];
	$data = $request->getParsedBody();

	if (!$data['username'] || !$data['password']) {
		$this->flash->addMessage('error', 'Veuillez entrer un nom d\'utilisateur et un mot de passe.');

		return $response->withStatus(302)->withHeader('Location', '/');
	}

	// Connection initialisation
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, $settings['userAgent']);
	curl_setopt($ch, CURLOPT_COOKIEFILE, $settings['cookieFile']);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);

	// Get login unique ID
	curl_setopt($ch, CURLOPT_URL, $settings['entLoginUrl']);
	$loginPage = curl_exec($ch);

	if(curl_errno($ch)) {
		$this->flash->addMessage('error', 'Erreur de connexion à l\'ENT.');

		return $response->withStatus(302)->withHeader('Location', '/');
	}

	$html = new simple_html_dom();
	$html->load($loginPage);
	$uniqueId = $html->find('input[name=lt]', 0)->value;

	// Connection
	$loginPostData = 'username=' . $data['username'] . '&password=' . $data['password'] . '&lt=' . $uniqueId . '&_eventId=submit&submit=SE+CONNECTER';
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $loginPostData);
	curl_setopt($ch, CURLOPT_REFERER, $settings['entLoginUrl']);
	$loggedPage = curl_exec($ch);

	$html->load($loggedPage);
	$connectionError = $html->find('div[id=status]', 0);
	if ($connectionError && $connectionError->plaintext == 'Les informations transmises n\'ont pas permis de vous authentifier.') {
		$this->flash->addMessage('error', 'Nom d\'utilisateur ou mot de passe incorrect.');

		return $response->withStatus(302)->withHeader('Location', '/');
	}

	// Getting grades page
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_URL, $settings['entGradesUrl']);
	curl_setopt($ch, CURLOPT_POST, false);
	$gradesSelectionPage = curl_exec($ch);

	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, 'sem=SEM26173');
	$gradesPage = curl_exec($ch);

	// Clear connection
	curl_close($ch);
	file_put_contents('cookie.txt', '');

	// Test if able to access student's grades
	$html->load($gradesPage);
	$studentName = $html->find('h3', 0);
	if (!$studentName) {
		$this->flash->addMessage('error', 'Désolé, votre formation ne met pas à disposition vos notes sur l\'ENT.');

		return $response->withStatus(302)->withHeader('Location', '/');
	}

	// Conversion grades -> array
	$notes = [];
	$bonus = [];
	$table = $html->find('table.notes_bulletin', 0);
	foreach ($table->find('tr.notes_bulletin_row_ue') as $ue) {
		$ueName = trim($ue->find('td', 0)->plaintext);
		if (substr($ueName, 0, 5) == 'Bonus') { // Bonus
			$nextModule = $ue->next_sibling();

			while ($nextModule && $nextModule->class == 'notes_bulletin_row_mod') {
				$bonusName = trim($nextModule->children(2)->plaintext);
				$bonusValue = floatval($nextModule->children(4)->plaintext);
				list($bonusMin, $bonusMax) = explode('/', trim($nextModule->children(5)->plaintext, '()'));

			 	$bonus[$ueName][$bonusName]['bonus'] = $bonusValue;
			 	$bonus[$ueName][$bonusName]['max'] = $bonusMax;
			 	$bonus[$ueName][$bonusName]['min'] = $bonusMin;

			 	$nextModule = $nextModule->next_sibling();
			 }
		} else {
			$ueCoeff = $ue->find('td', 6)->plaintext;
			$notes[$ueName]['coeff'] = $ueCoeff;

			$nextModule = $ue->next_sibling();
			$currentModule = '';
			while ($nextModule && $nextModule->class != 'notes_bulletin_row_ue') {
				if ($nextModule->class == 'notes_bulletin_row_mod') { // Module
					$currentModule = trim($nextModule->children(2)->plaintext);
					$moduleAverage = $nextModule->children(4)->plaintext;
					list($moduleMin, $moduleMax) = explode('/', trim($nextModule->children(5)->plaintext, '()'));
					$moduleCoeff = $nextModule->children(6)->plaintext;

					$notes[$ueName]['modules'][$currentModule]['moyenne'] = $moduleAverage;
					$notes[$ueName]['modules'][$currentModule]['max'] = $moduleMax;
					$notes[$ueName]['modules'][$currentModule]['min'] = $moduleMin;
					$notes[$ueName]['modules'][$currentModule]['coeff'] = $moduleCoeff;
				} elseif ($nextModule->class == 'toggle4') { // Contrôle
					$testName = $nextModule->children(3)->plaintext;
					$grade = floatval($nextModule->children(4)->plaintext);
					$coeff = trim($nextModule->children(6)->plaintext, '()');

					$notes[$ueName]['modules'][$currentModule]['notes'][$testName]['note'] = $grade;
					$notes[$ueName]['modules'][$currentModule]['notes'][$testName]['coeff'] = $coeff;
				}
				$nextModule = $nextModule->next_sibling();
			}
		}
	}

	// Total bonus calculation
	$totalBonus = 0;
	foreach ($bonus as $bonusUe) {
		foreach ($bonusUe as $module) {
			$totalBonus += $module['bonus'];
		}
	}

	// Global average calculation
	$totalNotes = 0;
	$totalCoefficients = 0;
	foreach ($notes as $ue) {
		foreach ($ue['modules'] as $module) {
			$totalNotes += $module['moyenne'] * $module['coeff'];
			$totalCoefficients += $module['coeff'];
		}
	}
	$globalAverage = round($totalNotes / $totalCoefficients, 2) + $totalBonus;

	list($firstName, $lastName) = explode('.', $data['username']);

	return $this->renderer->render($response, 'notes.phtml', ['prenom' => ucfirst($firstName), 'nom' => ucfirst($lastName), 'notes' => $notes, 'bonusUe' => $bonus, 'moyenneGenerale' => $globalAverage, 'bonusTotal' => $totalBonus, 'router' => $this->router]);
})->setName('notes');

<?php
// PHP equivalent of the Next.js API

// Assuming you have already included the necessary PHP libraries

function handler($req, $res, $next) {
  try {
    // Handle form data
    $data = $_FILES['file']; // Assuming it's a file upload
    $fileName = $data['name'] ?? "file";
    
    // Read file contents
    $fileContent = file_get_contents($data['tmp_name']);
    
    // Convert file content to base64
    $base64data = base64_encode($fileContent);

    // GitHub API URL
    $githubUrl = "https://api.github.com/repos/" . $_ENV['ORG_NAME'] . "/" . $_ENV['REPO_NAME'] . "/contents/" . $fileName;

    // GitHub API Headers with Personal Access Token
    $headers = [
      'Authorization: token ' . $_ENV['GITHUB_AUTH_TOKEN'],
      'Content-Type: application/json',
    ];

    try {
      // Check if file exists
      $response = curlGitHub($githubUrl, 'GET', null, $headers);
      $responseBody = json_decode($response['body'], true);
      $sha = $responseBody['sha'];

      // Update file
      $commitMessage = "Update " . $fileName;
      $commitData = [
        'message' => $commitMessage,
        'content' => $base64data,
        'branch' => 'main', // Assuming you're working with the main branch
        'sha' => $sha, // Include the sha for updating the existing file
      ];

      $commitResponse = curlGitHub($githubUrl, 'PUT', json_encode($commitData), $headers);
      $commitResponseBody = json_decode($commitResponse['body'], true);

      $fileUrl = $commitResponseBody['content']['download_url'];
      $commitUrl = $commitResponseBody['commit']['html_url'];

      // Return uploaded file URL and commit URL
      $jsdelivr = "https://cdn.jsdelivr.net/gh/" . $_ENV['ORG_NAME'] . "/" . $_ENV['REPO_NAME'] . "@" . $commitResponseBody['commit']['sha'] . "/" . $fileName;
      return json_encode(['fileurl' => $jsdelivr, 'size' => $commitResponseBody['content']['size'], 'fileUrl' => $fileUrl, 'commitUrl' => $commitUrl]);

    } catch (Exception $error) {
      if ($error->getCode() === 404) {
        // File doesn't exist, create a new one
        $commitMessage = "Upload " . $fileName;
        $commitData = [
          'message' => $commitMessage,
          'content' => $base64data,
          'branch' => 'main', // Assuming you're working with the main branch
        ];
  
        $commitResponse = curlGitHub($githubUrl, 'PUT', json_encode($commitData), $headers);
        $commitResponseBody = json_decode($commitResponse['body'], true);
  
        $fileUrl = $commitResponseBody['content']['download_url'];
        $commitUrl = $commitResponseBody['commit']['html_url'];
  
        // Return uploaded file URL and commit URL
        $jsdelivr = "https://cdn.jsdelivr.net/gh/" . $_ENV['ORG_NAME'] . "/" . $_ENV['REPO_NAME'] . "@" . $commitResponseBody['commit']['sha'] . "/" . $fileName;
        return json_encode(['fileurl' => $jsdelivr, 'size' => $commitResponseBody['content']['size'], 'fileUrl' => $fileUrl, 'commitUrl' => $commitUrl]);
      } else {
        error_log('Error updating file on GitHub: ' . $error->getMessage());
        // Handle other errors
      }
    } 

  } catch (Exception $error) {
    error_log('Error handling file: ' . $error->getMessage());
    return json_encode(['error' => 'Error handling file']);
  }
}

function curlGitHub($url, $method, $data = null, $headers = []) {
  $ch = curl_init($url);

  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  if ($data !== null) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  }

  if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  }

  $response = curl_exec($ch);
  $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  curl_close($ch);

  return ['body' => $response, 'statusCode' => $statusCode];
}

// Assuming this is the entry point for the API
$result = handler($_REQUEST, null, null);
echo $result;

?>

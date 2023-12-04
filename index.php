<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Crawler</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }

        input {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
        }

        button {
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }

        button:hover {
            background-color: #45a049;
        }

        ul {
            list-style-type: none;
            padding: 0;
        }

        ul li {
            margin-bottom: 5px;
        }

        a {
            text-decoration: none;
            color: #0066cc;
        }
    </style>
</head>
<body>
    <h2>URL Crawler</h2>
    <!-- Form for user input, initiating the search functionality -->
<form method="post">

<!-- Label prompting the user to enter a string for search -->
<label for="searchString">Enter a string to search:</label>

<!-- Input field for the user to input the search string -->
<input type="text" id="searchString" name="searchString" required>

<!-- Button triggering the form submission for the search -->
<button type="submit">Search</button>

</form>
    
<?php
// Set the maximum execution time to 1000 seconds (16 minutes and 40 seconds)
set_time_limit(1000);

// Define the starting point for web crawling. we can Add or modify URLs as needed.
$urlArray = [
    "https://cdnjs.com/libraries/font-awesome",
  ];

// File to store $crawled URLs
$filename = 'crawled_urls.txt';
// Clear existing content in the file or create an empty file
file_put_contents($filename, "", LOCK_EX);

// Global arrays to track $crawled and crawling URLs
$crawled = array(); //already $crawled
$current_url= array(); //currently being $crawled

// Set the User-Agent for the web crawler
$user_agent = 'MyWebCrawler/1.0';

$matching_url = array();

// Set the maximum depth level for the spider crawl.
$max_depth = 3;

/**
 * Function to append content to a file with locking mechanism
 * 
 * @param string $filename - The name of the file
 * @param string $content  - The content to be appended to the file
 */
function append_to_file($filename, $content) {
    
    file_put_contents($filename, $content,  FILE_APPEND | LOCK_EX);
}

/**
 * Fetches details from the specified URL, such as title, description, keywords, and text content.
 * 
 * @param string $url - The URL of the page to fetch details from
 * @return string - JSON string containing page details (title, description, keywords, URL, text content)
 */

function fetch_data($url) {
    global $user_agent;

    // Set User-Agent for the HTTP request

    // Prepare options to modify the User Agent for the HTTP request
    $options = array('http' => array('method' => "GET", 'headers' => "User-Agent: $user_agent\n"));
    // Create the stream context with the specified options
    $context = stream_context_create($options);

    // Fetch the HTML content of the page using the specified URL and context
    $htmlContent = @file_get_contents($url, false, $context);

    // Handle cases where fetching content fails or content is empty
    if ($htmlContent === false || empty($htmlContent)) {
        // Handle the case where fetching content fails or content is empty.
        return '';
    }

    // Load HTML content into the DOMDocument object from the fetched page.
    // The '@' symbol is used to suppress warnings or errors during the parsing process.
    // We attempt to load HTML content twice to ensure robustness in case of parsing issues.
    $doc = new DOMDocument();
    @$doc->loadHTML($htmlContent);
    @$doc->loadHTML(@file_get_contents($url, false, $context));

    // Create an array of all of the title tags.
    $title = $doc->getElementsByTagName("title");

    // Check if $title is not null and if it has at least one item.
    if ($title !== null && $title->length > 0) {
        // There should only be one <title> on each page, so our array should have only 1 element.
        $title = $title->item(0)->nodeValue;
    } else {
        // Handle the case where no title is found.
        $title = "No Title Found";
    }

    // Retrieve all meta tags for additional details.
    $metas = $doc->getElementsByTagName("meta");

    // Initialize variables for description and keywords, preventing potential errors.
    $description = "";
    $keywords = "";
    $text_content = ""; // To store the main text content
    
    // Loop through meta tags to get description and keywords.
    for ($i = 0; $i < $metas->length; $i++) {
        $meta = $metas->item($i);

        // Check if the meta tag corresponds to the description and keywords
        if (strtolower($meta->getAttribute("name")) == "description")
            $description = $meta->getAttribute("content");
        if (strtolower($meta->getAttribute("name")) == "keywords")
            $keywords = $meta->getAttribute("content");
    }

    // Extract text content from paragraphs (p tags) in the HTML document.
    $paragraphs = $doc->getElementsByTagName("p");
    // Loop through each paragraph, appending its text content to the variable.
    foreach ($paragraphs as $paragraph) {
        $text_content .= $paragraph->nodeValue . "\n";
    }

    // Limit the length of the text content to ensure concise output.
    $max_text_length = 200;
    $text_content = substr($text_content, 0, $max_text_length) . (strlen($text_content) > $max_text_length ? '...' : '');

    // Return our JSON string containing the title, description, keywords, URL, and additional details.
    return '{ "Title": "' . str_replace("\n", "", $title) . '", "Description": "' . str_replace("\n", "", $description) . '", "Keywords": "' . str_replace("\n", "", $keywords) . '", "URL": "' . $url . '", "TextContent": "' . str_replace("\n", "", $text_content) . '"},';
}

// Check if the given URL is allowed by the rules specified in robots.txt.
function is_allowed_by_robots_txt($url) {
    global $user_agent;

    // Generate the URL for the robots.txt file based on the provided URL.
    $robots_url = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . '/robots.txt';

    // Attempt to fetch the content of the robots.txt file.
    $robots_content = @file_get_contents($robots_url);

    // If robots.txt content is successfully retrieved.
    if ($robots_content !== false) {
        // Split the rules by lines and iterate through each rule.
        $rules = preg_split('/[\r\n]+/', $robots_content);

        foreach ($rules as $rule) {
            $rule = trim($rule);

            // Check if the rule specifies user-agent.
            if (stripos($rule, 'User-agent:') === 0) {
                $user_agent_match = trim(substr($rule, strlen('User-agent:')));
            } elseif (stripos($rule, 'Disallow:') === 0) {
                // Extract the disallowed path specified in the rule.
                $disallowed_path = trim(substr($rule, strlen('Disallow:')));

                // If the user agent matches the specified agent in robots.txt.
                if ($user_agent_match === $user_agent) {
                    // If no specific path is disallowed or the URL does not match the disallowed path.
                    if ($disallowed_path === '' || strpos($url, $disallowed_path) !== 0) {
                        return true; // Allowed by robots.txt
                    } else {
                        return false; // Disallowed by robots.txt
                    }
                }
            }
        }
    }

    // If no robots.txt or no specific disallow rules, assume it's allowed.
    return true;
}

// Search for the specified string within the provided text content.
function search_content($url, $searchString, $text_content) {
    // Check if the search string is found in the text content.
    if (stripos($text_content, $searchString) !== false || str_contains($text_content, $searchString)) {
        // Return the URL if the search string is found.
        return $url;
    }

    // If the search string is not found in the text content.
    return null;
}

/**
 * Function to follow links from a given URL up to a specified depth.
 *
 * @param string $url - The URL to start crawling from.
 * @param int $depth - The current depth level of the crawl.
 * @param string $searchString - The string to search for in the content.
 */
function crawling_urls($url, $depth = 1, $searchString) {
    // Check if the URL is allowed by robots.txt before proceeding.
    if (!is_allowed_by_robots_txt($url)) {
        return;
    }

    global $crawled;
    global $crawling;
    global $user_agent;
    global $max_depth;

    // The array that we pass to stream_context_create() to modify our User Agent.
    $options = array('http' => array('method' => "GET", 'headers' => "User-Agent: $user_agent\n"));
    // Create the stream context.
    $context = stream_context_create($options);

    // Use file_get_contents() to download the page, pass the output of file_get_contents()
    // to PHP's DOMDocument class.
    $htmlContent = @file_get_contents($url, false, $context);

    if ($htmlContent === false || empty($htmlContent)) {
        // Handle the case where fetching content fails or content is empty.
        return;
    }

    // Create a new instance of PHP's DOMDocument class.
    $doc = new DOMDocument();
    @$doc->loadHTML($htmlContent);

    // Create an array of all the links found on the page.
    $list_of_urls = $doc->getElementsByTagName("a");
    
    // Loop through all the links we may encounter and making them proper urls so we can crawl furthur
    foreach ($list_of_urls as $link) {
        $l = $link->getAttribute("href");

        // Process the links based on various conditions.
        if (substr($l, 0, 1) == "/" && substr($l, 0, 2) != "//") {
            // Handle relative paths.
            $l = parse_url($url)["scheme"] . "://" . parse_url($url)["host"] . $l;
        } else if (substr($l, 0, 2) == "//") {
            // Handle links starting with "//".
            $l = parse_url($url)["scheme"] . ":" . $l;
        } else if (substr($l, 0, 2) == "./") {
            // Handle links starting with "./".
            $l = parse_url($url)["scheme"] . "://" . parse_url($url)["host"] . dirname(parse_url($url)["path"]) . substr($l, 1);
        } else if (substr($l, 0, 1) == "#") {
            // Handle links starting with "#".
            $l = parse_url($url)["scheme"] . "://" . parse_url($url)["host"] . parse_url($url)["path"] . $l;
        } else if (substr($l, 0, 3) == "../") {
            // Handle links starting with "../".
            $l = parse_url($url)["scheme"] . "://" . parse_url($url)["host"] . "/" . $l;
        } else if (substr($l, 0, 11) == "javascript:") {
            // Skip processing JavaScript links.
            continue;
        } else if (substr($l, 0, 5) != "https" && substr($l, 0, 4) != "http") {
            // Handle links without "http" or "https" prefix.
            $l = parse_url($url)["scheme"] . "://" . parse_url($url)["host"] . "/" . $l;
        } else if (substr($l, 0, 5) == "data:" || 
                 substr($l, 0, 7) == "mailto:" || 
                 substr($l, 0, 4) == "ftp:" || 
                 substr($l, 0, 4) == "tel:") {
            // Skip processing data URLs, mailto links, FTP links, or tel links.
            continue;
        }

        // If the link isn't already in our crawl array and the depth is within limits.
        if (!in_array($l, $crawled) && $depth < $max_depth) {
            $crawled[] = $l;
            $crawling[] = $l;

            // Output the page details and append to the $crawled URLs file.
            $details = fetch_data($l) . "\n";
            global $filename;
            append_to_file($filename, $details . PHP_EOL);
            
            // Extract text content from the page.
            $paragraphs = $doc->getElementsByTagName("p");
            $text_content = "";
            foreach ($paragraphs as $paragraph) {
                $text_content .= $paragraph->nodeValue . "\n";
            }

            global $matching_url;
            // Search for the specified content within the text content.
            $searched_content = search_content($url, $searchString, $text_content);
            if ($searched_content != NULL) {
                // Add the matching URL to the array.
                $matching_url[] = $l;
            }

            // Follow the link with increased depth level.
            crawling_urls($l, $depth + 1, $searchString);
        }
    }

    // Remove an item from the array after we have $crawled it to prevent infinite crawling of the same page.
    array_shift($crawling);
}



/**
 * Initialize the variable to avoid an undefined variable warning.
 */
$searchString = "";

// Check if the form has been submitted via POST method.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize the search string from the form.
    $searchString = htmlspecialchars($_POST["searchString"]);

    //for eachurl inn the array call the function
    foreach ($urlArray as $url) {
        crawling_urls($url, 1, $searchString);
    }
}

// Output the matching URLs in an unordered list.
echo "<ul>";
foreach ($matching_url as $url) {
    // Display each matching URL as a list item with a hyperlink.
    echo "<li><a href=\"$url\" target=\"_blank\">$url</a></li>";
    echo "<br/><br/>";
}

echo "</ul>";
?>

</body>
</html>

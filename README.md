# GCrawler
GCrawler is a composer library. It is a custom-built web crawler to scrape search results specifically from Google

## Installation
``` composer require seneview/gcrawler-pub dev-master ```

## Usage
```
$client = new SearchEngine();

$client->setEngine('google.com');

# returns ResultObject Array
$results = $client->search(['keyword1', 'keyword2']);

# Show results from the first page
$results->first_page();

# Show results from 4th page
$results->page(4)

# Get total number of results crawled
$results->count();
```

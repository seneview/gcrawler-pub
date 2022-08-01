# GCrawler
GCrawler is a composer library. It is a custom-built web crawler to scrape search results specifically from Google

## Installation
``` composer require seneview/gcrawler ```

## Usage
```
$client = new SearchEngine();

client->setEngine('google.com');

# returns ResultObject Array
$results = client->search(['web', 'crawler']);
```

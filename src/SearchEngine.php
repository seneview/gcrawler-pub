<?php declare(strict_types=1);

namespace Seneview\Gcrawler;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;

/**
 * Main Class
 * Responsible for searching keywords in the  Google
 * and generating the Result
 */
class SearchEngine
{
    /**
     * @var array
     * Result cards found on the page
     */
    private array $_result_set = [];
    /**
     * @var string
     * Search Engine
     */
    private string $_engine;
    /**
     * @var string
     * Result card's main header type
     */
    private string $result_header_tag;
    /**
     * Keywords needed to be searched
     * @var array
     */
    private array $keywords;
    /**
     * Result iterator for extracted data
     * @var Result
     */
    public Result $rs;

    public function __construct()
    {
        $this->rs = new Result();
    }

    /**
     * Search engine setup
     * @param string $engine
     * @return void
     */
    public function setEngine(string $engine): void
    {
             // Remove tailing slash
            if ($engine[strlen($engine) - 1] === '/') {
                $engine = substr($engine, 0, strlen($engine) - 1);
            }
            // Adding URL protocol if not exists
            if (strpos($engine, "http://") === false && strpos($engine, "https://") === false) {
                $engine = "https://" . $engine;
            }
            $this->_engine = $engine;
    }

    /**
     * Main function of returning paginated result summarized
     * upto 50 results
     * @param array $keywords to search against
     * @return Result ResultObject
     * @throws GuzzleException
     */
    public function search(array $keywords): Result
    {
        // get results for 5 pages
        foreach (range(0, 4) as $pg) {
            $this->page_results($keywords, $pg);
        }
        return $this->rs;
    }

    /**
     * Recursively get results from search engine
     * and extract required data
     * @param array $keywords to search against
     * @param int $pg page number of search result
     * @return void
     * @throws Exception
     * @throws GuzzleException
     */
    private function page_results(array $keywords, int $pg): void
    {
        $query = $this->build_url($keywords, $pg);
        $response = $this->send_request($query);
        $this->analyse_results($response);
        $rank = 1;
        foreach ($this->_result_set as $result) {
            $title = $this->get_title($result);
            $url = $this->get_link($result);
            $description = $this->get_description($result);
            $promoted = $this->is_promoted($result);
            if ($title !== "") {
                $this->rs->append([
                    "title" => $title,
                    "url" => $url,
                    "description" => $description,
                    "promoted" => $promoted,
                    "rank" => $rank
                ]);
                $rank++;
            }
        }
    }

    /**
     * Builds the query based on keywords and result page number
     * @param array $keywords
     * @param int $pg
     * @return string Search query
     * @throws Exception
     */
    private function build_url(array $keywords, int $pg=1): string
    {
        // Return Google Search query
        if(count($keywords) <= 0){
            throw new InvalidArgumentException('No Keywords Provided');
        }
        $query_concat = implode("+", $keywords);
        if ($pg === 0) {
            return $this->_engine . "/search?q=" . preg_replace('/\s/', "+", $query_concat) . '&num=10';
        } else {
            $offset = $pg * 10;
            return $this->_engine . "/search?q=" . preg_replace('/\s/', "+", $query_concat) . '&num=10&start=' . $offset;
        }
    }

    /**
     * Remote GET request to search engine
     * @param string $query
     * @return string HTML Response
     * @throws GuzzleException
     */
    private function send_request(string $query): string
    {
        $client = new Client();
        $res = "";
        return $client->request('GET', $query)->getBody()->getContents();
    }

    /**
     * Finding the accurate results' element from the DOM
     * The first step is to determine the repeating header tag pattern, since every main result consists of a header
     * After that, climb up the parent node until all header ancestors are siblings
     * While looping through data, store and extract it
     * @param string $html
     * @return void
     */
    private function analyse_results(string $html): void
    {
        // Get all repeating headers
        $repeating_headers = $this->_get_repeating_headers($html);
        // Generating Result cards array
        for ($i = 0; $i < $repeating_headers->count(); $i++) {
            // Handling variate result count
            if ($repeating_headers[$i + 1]) {
                $this->result_cards($repeating_headers[$i], $repeating_headers[++$i]);
            } else {
                $this->result_cards($repeating_headers[$i - 1], $repeating_headers[$i]);
            }
        }
    }

    /**
     * Analyzing HTML for the most repeated header tags
     * @param string $html HTML response
     * @return DOMNodeList Most repeating header list
     */
    private function _get_repeating_headers(string $html): DOMNodeList
    {
        $repeating_header = "";
        $repetition = 1;
        $dom = new DOMDocument();
        @ $dom->loadHTML($html);
        foreach ([2, 3, 4, 5] as $size) { // getting 4n time complexity
            $header = $dom->getElementsByTagName('h' . $size); // Case sensitive cannot use H3,H4,...
            $header_count = $header->count();
            if ($header_count > $repetition) {
                $repetition = $header_count;
                $repeating_header = 'h' . $size;
            }
        }
        $this->result_header_tag = $repeating_header;
        return $dom->getElementsByTagName($repeating_header);
    }

    /**
     * Climbing up the parent hierarchy  until finding the common ancestor and
     * storing each element's ancestor which is the individual result of a search
     * @param DOMElement $node1
     * @param DOMElement $node2
     * @return void
     */
    private function result_cards(DOMElement $node1, DOMElement $node2): void
    {
        if ($node1->parentNode === $node2->parentNode) {
            $this->_result_set[] = $node1;
            $this->_result_set[] = $node2;
        } else {
            $this->result_cards($node1->parentNode, $node2->parentNode);
        }
    }

    /**
     * Extracting header from the individual result Element
     * @param $result
     * @return string title text
     */
    private function get_title($result): string
    {
        $headers = $result->getElementsByTagName($this->result_header_tag);
        // Some result doesnt have headers?
        $title = "";
        foreach ($headers as $header) {
            $title = $header->textContent;
            break;
        }
        return $title;
    }

    /**
     * Extracting link from the individual result Element
     * Each extracted link needed be sanitized
     * Removing prefixed string and query variables
     * @param DOMElement $node
     * @return string
     */
    private function get_link(DOMElement $node): string
    {
        $anchors = $node->getElementsByTagName('a');
        $href = "";
        foreach ($anchors as $anchor) {
            $href = $anchor->getAttribute('href');
        }

        $urlpos = strpos($href, 'http');
        if ($urlpos !== 0) {
            $href_parts = parse_url($href);
            $query = [];
            parse_str($href_parts['query'] ?? "", $query);
            $href = $query['q'] ?? "";
        }
        return $href;
    }

    /**
     * Extracting description from the individual result Element
     * @param DOMElement $node
     * @return string Description text
     */
    private function get_description(DOMElement $node): string
    {
        // Removing anchors to isolate description text
        $elem = $this->remove_elements($node, 'a');
        return $elem->textContent;
    }

    /**
     * A check should be made to see if it is an advertisement
     * @param DOMElement $node
     * @return bool
     */
    private function is_promoted(DOMElement $node): bool
    {
        preg_match('/Ad·http/', $node->textContent, $match);
        return count($match) > 0;
    }
    /**
     * @return string
     */
    public function getEngine(): string
    {
        return $this->_engine;
    }

    /**
     * Remove child node from given parent element
     * @param DOMElement $elem parent element
     * @param string $tag_name child node tag name to be removed
     * @return DOMElement modified parent element
     */
    private function remove_elements(DOMElement $elem, string $tag_name): DOMElement
    {
        $unwanted_elems = $elem->getElementsByTagName("d");
        while ($unwanted_elems->count() > 0) {
            $unwanted_elems->item(0)->parentNode->removeChild($unwanted_elems->item(0));
        }
        return $elem;
    }

}
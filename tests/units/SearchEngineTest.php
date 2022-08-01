<?php
/**
 * DO NOT RUN ALl TESTS AT ONCE!!!
 */

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\TestCase;
use Seneview\Gcrawler\Result;
use Seneview\Gcrawler\SearchEngine;

final class SearchEngineTest extends TestCase
{
    private SearchEngine $sEngine;

    /**
     * @throws ReflectionException
     */
    protected static function getMethod($name): ReflectionMethod
    {
        $class = new ReflectionClass(SearchEngine::class);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->sEngine = new SearchEngine();
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function test_search_with_results_dump() {
        $this->sEngine->setEngine('https://google.ae');
        var_dump($this->sEngine->search(['web', 'crawler']));
    }

    public function test_set_engine_append_protocol_to_search_engine():void{
        $this->sEngine->setEngine('google.com');
        $this->assertEquals('https://google.com', $this->sEngine->getEngine());
    }
    public function test_set_engine_remove_tailing_slash():void{
        $this->sEngine->setEngine('google.com/');
        $this->assertEquals('https://google.com', $this->sEngine->getEngine());
    }
    public function test_set_engine_doesnt_change_the_existing_protocol():void{
        $this->sEngine->setEngine('http://google.com/');
        $this->assertEquals('http://google.com', $this->sEngine->getEngine());
    }

    /**
     * @throws ReflectionException
     */
    public function test_build_query_returns_a_valid_url():void{
        $this->sEngine->setEngine('google.com');
        $_func = self::getMethod('build_url');
        $url = $_func->invokeArgs($this->sEngine, [['Query','parameter']]);
        $url_segments = parse_url($url);
        $this->assertStringContainsString('Query+parameter', $url_segments['query']);
    }

    /**
     * @throws ReflectionException
     */
    public function test_build_url_returns_start_offset_when_page_parameter_passed():void{
        $this->sEngine->setEngine('google.com');
        $_func = self::getMethod('build_url');
        $url = $_func->invokeArgs($this->sEngine, [['Query','parameter'], 2]);
        $url_segments = parse_url($url);
        $this->assertStringContainsString('&start=20', $url_segments['query']); // pages start from 0
    }

    /**
     * @throws ReflectionException
     */
    public function test_build_url_throws_an_exception_against_empty_keywords():void{
        $this->expectException(InvalidArgumentException::class);
        $this->sEngine->setEngine('google.com');
        $_func = self::getMethod('build_url');
        $url = $_func->invokeArgs($this->sEngine, [[], 2]);
    }

    /**
     * @throws ReflectionException
     */
    public function test_send_request_give_html_response():void{
        $this->sEngine->setEngine('google.com');
        $_func = self::getMethod('send_request');
        $res = $_func->invokeArgs($this->sEngine, ['https://google.com/search?q=hello+world']);
        $this->assertStringContainsString('</html>', $res);
    }

    /**
     * @throws ReflectionException
     */
    public function test_send_request_throw_exception_on_invalid_url():void{
        $this->expectException(ClientException::class);
        $this->sEngine->setEngine('google.com');
        $_func = self::getMethod('send_request');
        $res = $_func->invokeArgs($this->sEngine, ['https://google.com/search/?q=hello+world']);
    }

    /**
     * @throws GuzzleException
     */
    public function test_search_function_returns_Result_iterator():void{
        $this->sEngine->setEngine('google.com');
        $res = $this->sEngine->search(['Web','Crawler']);
        $this->assertInstanceOf(Result::class, $res);
    }

}
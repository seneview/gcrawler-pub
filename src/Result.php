<?php declare(strict_types=1);

namespace Seneview\Gcrawler;

use Exception;
use InvalidArgumentException;

/**
 * Paginated array
 */
class Result extends \ArrayIterator
{
    /**
     * @var int
     */
    protected int $perPage = 10;
    /**
     * @var int
     */
    protected int $currentPage = 1;

    /**
     * Pointing to current page in iterations
     * @return void
     */
    public function next():void
    {
        if( intval($this->count() / $this->perPage) > $this->current_page()) {
            $this->currentPage = intval($this->key() / $this->perPage) + 1;
            parent::next();
        }
    }

    /**
     * @return int
     */
    public  function current_page(): int
    {
        return $this->currentPage;
    }

    /**
     * @param int $pageNumber
     * @return array The  page result related to given page number
     * @throws Exception
     */
    public function page(int $pageNumber = 1):array{
        // PageNumber count from 1
        if($pageNumber < 0 || $pageNumber > intval($this->count() / $this->perPage) ){
            throw  new InvalidArgumentException('Page number out of bound');
        }
        if($this->count() < 0){
            throw  new Exception('No Result set cannot be empty');
        }
        $set = [];
        $offset = ($pageNumber -1) * $this->perPage;
        $this->seek($offset);
        while($this->valid()){
            $set[] = $this->current();
            if($this->key() == ($offset + $this->perPage) - 1){
                break;
            }
            $this->next();
        }

        return $set;
    }

    /**
     * @return array The next page result related to the current pointer
     * @throws Exception
     */
    public function  next_page(): array
    {
        if(intval($this->count() / $this->perPage) !== $this->current_page()) {
            return $this->page($this->current_page() + 1);
        }
        return [];
    }

    /**
     * @return array The previous page result related to the current pointer
     * @throws Exception
     */
    public function prev_page(): array
    {
        if($this->current_page() > 1) {
            return $this->page($this->current_page() + 1);
        }
        return [];
    }

    /**
     * @return array filtered result array of first page
     * @throws Exception
     */
    public function first_page(): array
    {
        return $this->page( 1);
    }

    /**
     * @return array filtered result array of last page
     * @throws Exception
     */
    public function last_page(): array
    {
        return $this->page( intval($this->count() / $this->perPage));
    }

}
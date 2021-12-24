<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

namespace App\Models\Extensions;

use App\Exceptions\Internal\QueryBuilderException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Expression;

/**
 * Fixed Eloquent query builder.
 *
 * This trait decorates some Eloquent builder methods to make error handling
 * more consistent.
 *
 * Some Eloquent builder methods throw spurious undocumented or documented
 * but impracticable exceptions.
 * "Impracticable" means that the exceptions are not specific to Eloquent,
 * although they originate from inside Eloquent (and not from another even
 * more basic function called by Eloquent).
 * This makes it difficult to specifically catch these exceptions on a higher
 * level of the call stack.
 * This leaves us with two options:
 * Either wrap each and every call to Eloquent into an exception handler
 * in-place like this
 *
 *     try {
 *       $models = MyModel::query()->where(...)->orderBy(...)->get();
 *     } catch (\Throwable $e) {
 *       throw new QueryBuilderException($e);
 *     }
 *
 * or use a decorator like this trait.
 * In order to keep our actual "business logic" clean from work-arounds for
 * awkward design decisions of Eloquent, we use this decorator.
 * Hopefully, the necessity for this trait will vanish in the future after
 * Eloquent has adopted to proper error handling.
 * See [Laravel Discussion #40020](https://github.com/laravel/framework/discussions/40020).
 *
 * _Note:_ This trait does not wrap every method of the underlying
 * {@link \Illuminate\Database\Eloquent\Builder}; only those which are used
 * by Lychee.
 */
trait FixedQueryBuilderTrait
{
	/**
	 * Add a basic where clause to the query.
	 *
	 * @param \Closure|string|array|Expression $column
	 * @param mixed                            $operator
	 * @param mixed                            $value
	 * @param string                           $boolean
	 *
	 * @return $this
	 *
	 * @throws QueryBuilderException
	 */
	public function where($column, $operator = null, $value = null, $boolean = 'and'): static
	{
		try {
			return parent::where($column, $operator, $value, $boolean);
		} catch (\Throwable $e) {
			throw new QueryBuilderException($e);
		}
	}

	/**
	 * Add a "where in" clause to the query.
	 *
	 * @param string $column
	 * @param mixed  $values
	 * @param string $boolean
	 * @param bool   $not
	 *
	 * @return $this
	 *
	 * @throws QueryBuilderException
	 */
	public function whereIn($column, $values, $boolean = 'and', $not = false): static
	{
		try {
			return parent::whereIn($column, $values, $boolean, $not);
		} catch (\Throwable $e) {
			throw new QueryBuilderException($e);
		}
	}

	/**
	 * Set the columns to be selected.
	 *
	 * @param array|mixed $columns
	 *
	 * @return $this
	 *
	 * @throws QueryBuilderException
	 */
	public function select($columns = ['*']): static
	{
		try {
			return parent::select($columns);
		} catch (\Throwable $e) {
			throw new QueryBuilderException($e);
		}
	}

	/**
	 * Add a join clause to the query.
	 *
	 * @param string          $table
	 * @param \Closure|string $first
	 * @param string|null     $operator
	 * @param string|null     $second
	 * @param string          $type
	 * @param bool            $where
	 *
	 * @return $this
	 *
	 * @throws QueryBuilderException
	 */
	public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false): static
	{
		try {
			return parent::join($table, $first, $operator, $second, $type, $where);
		} catch (\Throwable $e) {
			throw new QueryBuilderException($e);
		}
	}

	/**
	 * Add an "order by" clause to the query.
	 *
	 * @param \Closure|Builder|BaseBuilder|Expression|string $column
	 * @param string                                         $direction
	 *
	 * @return $this
	 *
	 * @throws QueryBuilderException
	 */
	public function orderBy($column, $direction = 'asc'): static
	{
		try {
			return parent::orderBy($column, $direction);
		} catch (\Throwable $e) {
			throw new QueryBuilderException($e);
		}
	}

	/**
	 * Add a new select column to the query.
	 *
	 * @param array|mixed $column
	 *
	 * @return $this
	 *
	 * @throws QueryBuilderException
	 */
	public function addSelect($column): static
	{
		try {
			return parent::addSelect($column);
		} catch (\Throwable $e) {
			throw new QueryBuilderException($e);
		}
	}

	/**
	 * Add an "or where" clause to the query.
	 *
	 * @param \Closure|array|string|Expression $column
	 * @param mixed                            $operator
	 * @param mixed                            $value
	 *
	 * @return $this
	 *
	 * @throws QueryBuilderException
	 */
	public function orWhere($column, $operator = null, $value = null): static
	{
		try {
			return parent::orWhere($column, $operator, $value);
		} catch (\Throwable $e) {
			throw new QueryBuilderException($e);
		}
	}
}
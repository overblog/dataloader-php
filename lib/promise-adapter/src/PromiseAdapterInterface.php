<?php

/*
 * This file is part of the DataLoaderPhp package.
 *
 * (c) Overblog <http://github.com/overblog/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Overblog\PromiseAdapter;

interface PromiseAdapterInterface
{
    /**
     * Creates a Promise
     *
     * @param $resolve
     * @param $reject
     * @param callable $canceller
     *
     * @return mixed a Promise
     */
    public function create(&$resolve = null, &$reject = null, callable $canceller = null);

    /**
     * Creates a full filed Promise for a value if the value is not a promise.
     *
     * @param mixed $promiseOrValue
     *
     * @return mixed a full filed Promise
     */
    public function createFulfilled($promiseOrValue = null);

    /**
     * Creates a rejected promise for a reason if the reason is not a promise. If
     * the provided reason is a promise, then it is returned as-is.
     *
     * @param mixed $reason
     *
     * @return mixed a rejected promise
     */
    public function createRejected($reason);

    /**
     * Given an array of promises, return a promise that is fulfilled when all the
     * items in the array are fulfilled.
     *
     * @param mixed $promisesOrValues Promises or values.
     *
     * @return mixed a Promise
     */
    public function createAll($promisesOrValues);

    /**
     * Check if value is a promise
     *
     * @param mixed $value
     * @param bool $strict
     *
     * @return bool
     */
    public function isPromise($value, $strict = false);

    /**
     * Cancel a promise
     *
     * @param $promise
     */
    public function cancel($promise);

    /**
     * wait for Promise to complete
     * @param mixed $promise
     * @param bool  $unwrap
     *
     * @return mixed
     */
    public function await($promise = null, $unwrap = false);
}

<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

/**
 * Common interface of classes in Kabomu library which perform
 * resource clean-up operations.
 */
interface CustomDisposable {

    /**
     * Gets a function which if invoked,
     * performs any needed clean up operation on resources held
     * by the instance.
     */
    function getDisposer(): ?\Closure;

    /**
     * Sets a function which if invoked,
     * performs any needed clean up operation on resources held
     * by the instance.
     * @param ?\Closure value
     */
    function setDisposer(?\Closure $value);
}

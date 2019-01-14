[![Build Status](https://travis-ci.org/uppes/promiseplus.svg?branch=master)](https://travis-ci.org/uppes/promiseplus)[![codecov](https://codecov.io/gh/uppes/promiseplus/branch/master/graph/badge.svg)](https://codecov.io/gh/uppes/promiseplus)

Promise /A+
=======
An full implementation of
[Promises](https://promisesaplus.com/) and __Deferreds__ for PHP.<a href="https://promisesaplus.com/">
    <img src="https://promisesaplus.com/assets/logo-small.png" alt="Promises/A+ logo"
         title="Promises/A+ 1.0 compliant" align="right" />
</a>

An Promise and an Defeered might seem identical. The main differences between Promises and Deferreds revolve around internal state and mutability.

## Promises
Promises are completely independent and, once fulfilled, immutable.  Each call to a Promise's `then()` method will produce a brand new Promise instance that depends on its parent's eventually fulfilled value.

Promises operate under the assumption that they'll be passed around to different parts of your code, that the chains will branch, and that the code they're calling will always result in an asynchronous result.

Use a *__Promise__* when you need to create multiple branches of intermediate results or you need to pass the Promise into code that you don't control.

## Deferreds
On the other hand, calling a Deferred's `then()` method will add callbacks to the internal dispatch chain of the Deferred and return the same instance.  Also, the internal state of a Deferred will mutate as it transitions between steps of the chain.

Use a *__Deferred__* if you want to build a fast, isolated, and synchronous dispatch chain that still honors asynchronous 'Thenable' results. 

## The API
Every Promise object will have Deferred Object, that contains the following methods. Most of these should be familiar coming from there JavaScript origin. Only the Promise constructor, accepts a callback if invoked is used to resolve or reject the promise.

`.isPending()` - Returns whether or not the Promise or Deferred is currently in a pending state.

`.isSettled()` - Returns whether or not the Promise or Deferred is in a settled (non-pending) state.

`.isFulfilled()` - Returns whether or not the Promise or Deferred is in a resolved (non-rejected) state.

`.isRejected()` - Returns whether or not the Promise or Deferred is in a rejected state.

`.resolve(result?:any)` - Resolves the Promise or Deferred with the specified result.

`.reject(reason?:any)` - Rejects the Promise or Deferred with the specified reason.

`.getResult()` - Returns the Result of the Promise or Deferred if it has been fulfilled.  If it has not been fulfilled, an uncaught Error is thrown.

`.getReason()` - Returns the reason that the Promise or Deferred has been rejected, if it has been rejected.  If it has not been rejected, an uncaught Error is thrown.

`.then(onFulfilled?:Function, onRejected?:Function)` - In the case of a Promise, creates a Promise whose value depends on its parent. In the case of a Deferred, adds an onFulfilled and/or onRejected handler to the dispatch chain.

`.done(onFulfilled?:Function, onRejected?:Function)` - Like `then()` but doesn't return a new Promise or Deferred.  Also, any uncaught exceptions inside one of its callbacks will be thrown uncaught on the next clock tick.

`.catch(onRejected?:Function)` - Same as 'then' except that only an `onRejected` callback is provided.

`.finally(onFinally?:Function)` - Will call the onFinally callback when the parent Promise or Deferred is either fulfilled or rejected.  Will not interrupt or modify further processing.

`.all()` - Creates a new Promise or Deferred whose eventually fulfilled value will be an Array containing the fulfilled results of each provided Promise or Deferred.

`.race()` - Creates a new Promise or Deferred whose eventually fulfilled value will be whichever provided Promise or Deferred is resolved or rejected first.

`.some(count:number)` - Creates a new Promise or Deferred whose eventually fulfilled value will be an Array containing the first `count` results fulfilled from the original Array.  If too few results can be fulfilled, the Promise or Deferred is rejected.

`.any()` - Effectively the same as `some()`, but with a count of 1, except that the resulting array is unwrapped, and the single element is provided as a fulfilled value.

The `Promise` interfaces also expose some additional capabilities:

`resolver(result)` - Creates and returns an immediately resolved Promise.

`rejecter(reason)` - Creates and returns an immediately rejected Promise.

In addition to these functions, versions of `all()`, `race()`, `some()`, and `any()` are provided.  These versions require an initial promise or Array from which to bootstrap.

## License (MIT License)
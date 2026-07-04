import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\MovementController::index
* @see app/Http/Controllers/MovementController.php:20
* @route '/movimientos'
*/
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/movimientos',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\MovementController::index
* @see app/Http/Controllers/MovementController.php:20
* @route '/movimientos'
*/
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MovementController::index
* @see app/Http/Controllers/MovementController.php:20
* @route '/movimientos'
*/
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\MovementController::index
* @see app/Http/Controllers/MovementController.php:20
* @route '/movimientos'
*/
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\MovementController::store
* @see app/Http/Controllers/MovementController.php:92
* @route '/movimientos'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/movimientos',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\MovementController::store
* @see app/Http/Controllers/MovementController.php:92
* @route '/movimientos'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MovementController::store
* @see app/Http/Controllers/MovementController.php:92
* @route '/movimientos'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\MovementController::reorder
* @see app/Http/Controllers/MovementController.php:168
* @route '/movimientos/reorder'
*/
export const reorder = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reorder.url(options),
    method: 'patch',
})

reorder.definition = {
    methods: ["patch"],
    url: '/movimientos/reorder',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\MovementController::reorder
* @see app/Http/Controllers/MovementController.php:168
* @route '/movimientos/reorder'
*/
reorder.url = (options?: RouteQueryOptions) => {
    return reorder.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\MovementController::reorder
* @see app/Http/Controllers/MovementController.php:168
* @route '/movimientos/reorder'
*/
reorder.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: reorder.url(options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\MovementController::update
* @see app/Http/Controllers/MovementController.php:116
* @route '/movimientos/{movement}'
*/
export const update = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

update.definition = {
    methods: ["put"],
    url: '/movimientos/{movement}',
} satisfies RouteDefinition<["put"]>

/**
* @see \App\Http\Controllers\MovementController::update
* @see app/Http/Controllers/MovementController.php:116
* @route '/movimientos/{movement}'
*/
update.url = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { movement: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { movement: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            movement: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        movement: typeof args.movement === 'object'
        ? args.movement.id
        : args.movement,
    }

    return update.definition.url
            .replace('{movement}', parsedArgs.movement.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\MovementController::update
* @see app/Http/Controllers/MovementController.php:116
* @route '/movimientos/{movement}'
*/
update.put = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: update.url(args, options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\MovementController::patch
* @see app/Http/Controllers/MovementController.php:116
* @route '/movimientos/{movement}'
*/
export const patch = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: patch.url(args, options),
    method: 'patch',
})

patch.definition = {
    methods: ["patch"],
    url: '/movimientos/{movement}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\MovementController::patch
* @see app/Http/Controllers/MovementController.php:116
* @route '/movimientos/{movement}'
*/
patch.url = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { movement: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { movement: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            movement: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        movement: typeof args.movement === 'object'
        ? args.movement.id
        : args.movement,
    }

    return patch.definition.url
            .replace('{movement}', parsedArgs.movement.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\MovementController::patch
* @see app/Http/Controllers/MovementController.php:116
* @route '/movimientos/{movement}'
*/
patch.patch = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: patch.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\MovementController::destroy
* @see app/Http/Controllers/MovementController.php:149
* @route '/movimientos/{movement}'
*/
export const destroy = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/movimientos/{movement}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\MovementController::destroy
* @see app/Http/Controllers/MovementController.php:149
* @route '/movimientos/{movement}'
*/
destroy.url = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { movement: args }
    }

    if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
        args = { movement: args.id }
    }

    if (Array.isArray(args)) {
        args = {
            movement: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        movement: typeof args.movement === 'object'
        ? args.movement.id
        : args.movement,
    }

    return destroy.definition.url
            .replace('{movement}', parsedArgs.movement.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\MovementController::destroy
* @see app/Http/Controllers/MovementController.php:149
* @route '/movimientos/{movement}'
*/
destroy.delete = (args: { movement: number | { id: number } } | [movement: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

const movimientos = {
    index: Object.assign(index, index),
    store: Object.assign(store, store),
    reorder: Object.assign(reorder, reorder),
    update: Object.assign(update, update),
    patch: Object.assign(patch, patch),
    destroy: Object.assign(destroy, destroy),
}

export default movimientos
<?php

declare(strict_types=1);

/*
 * This code is under BSD 3-Clause "New" or "Revised" License.
 *
 * PHP version 7 and above required
 *
 * @category  RoutingManager
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 Biurad Group (https://biurad.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * @link      https://www.biurad.com/projects/routingmanager
 * @since     Version 0.1
 */

namespace Flight\Routing\Interfaces;

use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Api Resource Controller.
 */
interface ResourceController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Psr\Http\Message\ResponseInterface|string
     */
    public function index();

    /**
     * Display the specified resource.
     *
     * @param Request $request
     * @param  int|mixed $id
     *
     * @return \Psr\Http\Message\ResponseInterface|string
     */
    public function show(Request $request, $id);

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     *
     * @return \Psr\Http\Message\ResponseInterface|string
     */
    public function store(Request $request);

    /**
     * Show the form for creating a new resource.
     *
     * @return \Psr\Http\Message\ResponseInterface|string
     */
    public function create();

    /**
     * Show the form for editing the specified resource.
     *
     * @param Request $request
     * @param  int|mixed $id
     *
     * @return \Psr\Http\Message\ResponseInterface|string
     */
    public function edit(Request $request, $id);

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int|mixed $id
     *
     * @return \Psr\Http\Message\ResponseInterface|string
     */
    public function update(Request $request, $id);

    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     * @param  id|mixed $id
     *
     * @return \Psr\Http\Message\ResponseInterface|string
     */
    public function destroy(Request $request, $id);

}

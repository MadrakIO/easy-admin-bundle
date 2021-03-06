<?php

namespace MadrakIO\Bundle\EasyAdminBundle\Controller;

interface MenuAwareControllerInterface
{
    /**
     * Returns a multi-dimensional array with routes and page titles
     * These routes are then used in the Menu
     *
     * @return array
     */
    public function getMenuRoutes();

    /**
     * Returns a string for an icon in the menu builder
     *
     * @return string
     */
    public function getMenuIcon();
}

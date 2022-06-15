import { createRouter, createWebHistory } from "vue-router";
import DefaultLayout from "../components/DefaultLayout.vue"
import AuthLayout from "../components/AuthLayout.vue"
import Dashboard from "../views/Dashboard.vue"
import Surveys from "../views/Surveys.vue"
import SurveyView from "../views/SurveyView.vue"
import Login from "../views/Login.vue"
import Register from "../views/Register.vue"
import SurveyPublicView from "../views/SurveyPublicView.vue";
import store from "../store";


const routes = [
  {
    // routes for authenticated
    path: '/',
    redirect: '/dashboard',
    name: 'DefaultLayout',
    component: DefaultLayout,
    meta: { requiresAuth: true },
    children: [
      { path: '/dashboard', name: 'Dashboard', component: Dashboard },
      { path: '/surveys', name: 'Surveys', component: Surveys },
      { path: '/surveys/create', name: 'SurveyCreate', component: SurveyView },
      { path: '/surveys/:id', name: 'SurveyView', component: SurveyView },
    ]
  },
  {
    // routes for guest
    path: '/auth',
    redirect: '/login',
    name: 'Auth',
    component: AuthLayout,
    meta: {isGuest: true},
    children: [
      { path: '/login', name: 'Login', component: Login },
      { path: '/register', name: 'Register', component: Register }
    ]
  },
  {
    // survey public route
    // :slug (parameter)
    path: '/view/survey/:slug',
    name: 'SurveyPublicView',
    component: SurveyPublicView
  }
];

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  //if the page requires `requiresAuth` & user token does not exist
  if (to.meta.requiresAuth && !store.state.user.token) {
    next({ name: 'Login' })
  //if the user token is authenticated & tries to access Guest route
  } else if (store.state.user.token && to.meta.isGuest) {
    next({ name: 'Dashboard' })
  } else {
    next()
  }
})

export default router;
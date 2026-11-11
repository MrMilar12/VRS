'use strict';

const maintenanceTablist = document.querySelector('[data-maintenance-tabs]');
if (maintenanceTablist) {
  const maintenanceTabs = [...maintenanceTablist.querySelectorAll('[data-maintenance-tab]')];
  const maintenancePanels = [...document.querySelectorAll('[data-maintenance-panel]')];
  const activateMaintenanceTab = (tab, moveFocus = false, updateHash = false) => {
    maintenanceTabs.forEach((item) => {
      const selected = item === tab;
      item.classList.toggle('is-active', selected);
      item.setAttribute('aria-selected', String(selected));
      item.tabIndex = selected ? 0 : -1;
    });
    maintenancePanels.forEach((panel) => {
      panel.hidden = panel.dataset.maintenancePanel !== tab.dataset.maintenanceTab;
    });
    if (moveFocus) tab.focus();
    if (updateHash) {
      const hash = tab.dataset.maintenanceTab === 'parts' ? '#parts' : '#schedule';
      if (window.location.hash !== hash) history.replaceState(null, '', hash);
    }
  };

  if (maintenanceTabs.length === 2 && maintenancePanels.length === 2) {
    const initialTab =
      maintenanceTabs.find((tab) => tab.dataset.maintenanceTab === window.location.hash.slice(1)) ??
      maintenanceTabs[0];
    activateMaintenanceTab(initialTab);
    maintenanceTablist.hidden = false;

    maintenanceTablist.addEventListener('click', (event) => {
      const tab = event.target.closest('[data-maintenance-tab]');
      if (tab && maintenanceTablist.contains(tab)) activateMaintenanceTab(tab, false, true);
    });
    maintenanceTablist.addEventListener('keydown', (event) => {
      const currentIndex = maintenanceTabs.indexOf(event.target);
      if (currentIndex < 0) return;
      let nextIndex;
      if (event.key === 'ArrowRight') nextIndex = (currentIndex + 1) % maintenanceTabs.length;
      else if (event.key === 'ArrowLeft')
        nextIndex = (currentIndex - 1 + maintenanceTabs.length) % maintenanceTabs.length;
      else if (event.key === 'Home') nextIndex = 0;
      else if (event.key === 'End') nextIndex = maintenanceTabs.length - 1;
      else return;
      event.preventDefault();
      activateMaintenanceTab(maintenanceTabs[nextIndex], true, true);
    });
  }
}

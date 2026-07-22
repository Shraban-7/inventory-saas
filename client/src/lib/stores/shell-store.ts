import { create } from "zustand";

interface ShellState {
  activeBranchId: number | null;
  sidebarCollapsed: boolean;
  commandPaletteOpen: boolean;
  setActiveBranchId: (branchId: number | null) => void;
  setSidebarCollapsed: (collapsed: boolean) => void;
  toggleSidebar: () => void;
  setCommandPaletteOpen: (open: boolean) => void;
  toggleCommandPalette: () => void;
}

export const useShellStore = create<ShellState>((set) => ({
  activeBranchId: 1, // Default to Branch #1
  sidebarCollapsed: false,
  commandPaletteOpen: false,
  setActiveBranchId: (branchId) => set({ activeBranchId: branchId }),
  setSidebarCollapsed: (collapsed) => set({ sidebarCollapsed: collapsed }),
  toggleSidebar: () => set((state) => ({ sidebarCollapsed: !state.sidebarCollapsed })),
  setCommandPaletteOpen: (open) => set({ commandPaletteOpen: open }),
  toggleCommandPalette: () => set((state) => ({ commandPaletteOpen: !state.commandPaletteOpen })),
}));
